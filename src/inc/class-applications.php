<?php declare( strict_types=1 );

namespace Transgression;

use WP_Post;

class Applications extends Singleton {
	const POST_TYPE = 'application';
	const COMMENT_TYPE = 'review_comment';
	const STATUS_APPROVED = 'approved';
	const STATUS_DENIED = 'denied';

	const LABELS = [
		'name' => 'Applications',
		'singular_name' => 'Application',
		'edit_item' => 'Review Application',
	];

	const FIELDS = [
		'post_title' => 'Name',
		'email' => 'Email',
		'pronouns' => 'Pronouns',
		'identity' => 'How they identify',
		'associates' => 'Who they know',
		'referrer' => 'How they know us',
		'warnings' => 'Potential conflicts',
		'accessibility' => 'Accessibility concerns',
		'extra' => 'Additional comments',
	];

	const MIME_TYPES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png' => 'image/png',
		'tiff|tif' => 'image/tiff',
		'webp' => 'image/webp',
	];

	protected function __construct() {
		// Actions
		add_action( 'save_post_' . self::POST_TYPE, [$this, 'save'] );
		add_action( 'post_action_verdict', [$this, 'action_verdict'] );

		// Display
		add_action( 'admin_enqueue_scripts', [$this, 'scripts'] );
		add_filter( 'post_updated_messages', [$this, 'update_messages'] );
		add_action( 'edit_form_top', [$this, 'render_status'] );
		add_action( 'post_edit_form_tag', function() { echo ' enctype="multipart/form-data"'; } );
		add_filter( 'post_row_actions', [$this, 'remove_bulk_actions'], 10, 2 );
		add_filter( 'comments_list_table_query_args', [$this, 'hide_review_comments'] );
	}

	public function init() {
		register_post_type( self::POST_TYPE, [
			'label' => self::LABELS['name'],
			'labels' => self::LABELS,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			'supports' => ['title', 'comments'],
			'register_meta_box_cb' => [$this, 'meta_boxes'],
			'delete_with_user' => true,
			'capabilities' => [
				'create_posts' => false,
			],
			'map_meta_cap' => true,
		] );

		register_post_status( self::STATUS_APPROVED, [
			'label' => 'Approved',
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>'
			),
		] );

		register_post_status( self::STATUS_DENIED, [
			'label' => 'Denied',
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop(
				'Denied <span class="count">(%s)</span>',
				'Denied <span class="count">(%s)</span>'
			),
		] );
	}

	public function save( int $post_id ) {
		// New comments.
		$new_comment = sanitize_textarea_field( $_POST['newcomment'] ?? '' );
		if ( $new_comment ) {
			$user = wp_get_current_user();
			wp_insert_comment( [
				'comment_author' => $user->display_name,
				'comment_post_ID' => $post_id,
				'comment_content' => $new_comment,
				'comment_type' => self::COMMENT_TYPE,
			] );
		}

		if ( empty( $_FILES['social_image'] ) ) {
			return;
		}
		$new_image = $_FILES['social_image'];
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

        $new_file = wp_handle_upload( $new_image, [ 'test_form' => false, 'mimes' => self::MIME_TYPES ] );

        if ( $new_file && !isset( $new_file['error'] ) ) {
            update_post_meta( $post_id, 'photo_img', $new_file['url'] );
        }
	}

	public function action_verdict( int $post_id ) {
		check_admin_referer( "verdict-{$post_id}" );
		$post = get_post( $post_id );
		if ( $post->post_type !== self::POST_TYPE || !current_user_can( 'edit_post', $post_id ) ) {
			wp_die();
		}

		$verdict = $_GET['verdict'];
		if ( $verdict === 'finalize' ) {
			$message = $this->finalize( $post );
		} else {
			$meta = [
				'approved' => $verdict === 'yes',
				'user_id' => get_current_user_id(),
				'date' => time(),
			];
			add_post_meta( $post_id, 'verdict', $meta );
			$message = 100;
		}

		$redirect = get_edit_post_link( $post->ID, 'url' );
		if ( $message ) {
			$redirect = add_query_arg( 'message', $message, $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function scripts() {
		$screen = get_current_screen();
		if (
			is_object( $screen ) &&
			$screen->post_type === self::POST_TYPE &&
			$screen->action === 'edit'
		) {
			wp_enqueue_style( 'application-styles', get_theme_file_uri( 'assets/apps-admin.css' ) );
		}
	}

	public function render_status( WP_Post $post ) {
		if ( $post->post_type !== self::POST_TYPE ) {
			return;
		}

		$status = $post->post_status;
		if ( $status === 'approved' ) {
			$user = get_userdata( $post->created_user );
			if ( !$user ) {
				echo '<div class="notice notice-warning"><p>Application for missing user!</p></div>';
				return;
			}
			printf(
				'<div class="notice notice-success"><p>%1$s <a href="%3$s">%2$s</a></p></div>',
				'Application is approved for',
				esc_html( $user->display_name ),
				esc_url( get_edit_user_link( $user->ID ) )
			);
		} else if ( $status === 'denied' ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				'This application is denied'
			);
		}
	}

	public function meta_boxes() {
		add_meta_box(
			self::POST_TYPE . '_fields',
			'Form Entry',
			[$this, 'render_metabox_fields'],
			self::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			self::POST_TYPE . '_comments',
			'Comments',
			[$this, 'render_metabox_comments'],
			self::POST_TYPE,
			'normal'
		);
		add_meta_box(
			self::POST_TYPE . '_verdict',
			'Verdict',
			[$this, 'render_metabox_verdict'],
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			self::POST_TYPE . '_photo',
			'Photo',
			[$this, 'render_metabox_photo'],
			self::POST_TYPE,
			'side',
			'high'
		);

		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'pageparentdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'commentstatusdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
	}

	public function render_metabox_fields( WP_Post $post ) {
		echo '<table class="app-fields"><tbody>';
		$fields = self::FIELDS;
		$original_field_keys = array_keys( $fields );
		if ( function_exists( '\\Transgression\\get_form_fields_for_meta' ) && $post->_form_id ) {
			$form_fields = get_form_fields_for_meta( absint( $post->_form_id ) );
			$fields = array_merge( $form_fields, $fields );
		}
		foreach ( $fields as $key => $name ) {
			$value = $post->{$key};
			if ( !$value && ! in_array( $key, $original_field_keys, true ) ) {
				continue;
			}
			echo '<tr>';
			printf(
				'<th>%s</th>',
				esc_html( $name )
			);
			if ( $value ) {
				if ( $key === 'email' ) {
					printf( '<td><a href="mailto:%1$s" target="_blank">%1$s</a></td>', esc_attr( $value ) );
				} else {
					$paragraphs = preg_split( '/\n\s*\n/', $value, -1, PREG_SPLIT_NO_EMPTY );
					echo '<td>';
					foreach ( $paragraphs as $paragraph ) {
						echo esc_html( wptexturize( trim( $paragraph ) ) ) . "<br/>\n";
					}
					echo '</td>';
				}
			} else {
				echo '<td><em>Not provided</em></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function render_metabox_comments( WP_Post $post ) {
		/** @var \WP_Comment[] */
		$comments = get_comments( [
			'post_id' => $post->ID,
			'order' => 'ASC',
			'type'=> self::COMMENT_TYPE,
		] );
		wp_list_comments( ['post_id' => $post->ID] );
		echo '<ul class="app-comments">';
		foreach ( $comments as $comment ) {
			echo '<li>';
			printf(
				'<q>%1$s</q> - %2$s, ',
				esc_html( $comment->comment_content ),
				esc_html( $comment->comment_author )
			);
			render_time( $comment->comment_date_gmt, true );
			echo '</li>';
		}
		echo '</ul>';
		echo '<hr />';
		echo '<p><textarea class="large-text app-newcomment" placeholder="Leave a comment" name="newcomment"></textarea></p>';
		submit_button();
		echo '<div class="clear"></div>';
	}

	public function render_metabox_verdict( WP_Post $post ) {
		$verdicts = $this->get_unique_verdicts( $post->ID );
		if ( count( $verdicts ) > 0 ) {
			echo '<ul>';
			foreach ( $verdicts as $verdict ) {
				$user = get_userdata( $verdict['user_id'] );
				echo '<li>';
				printf(
					'%s - %s, ',
					$verdict['approved'] === true ? '✅' : '❌',
					esc_html( $user->display_name ),
				);
				render_time( $verdict['date'], true );
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p><em>No verdicts yet</em></p>';
		}
		echo '<hr />';

		echo '<p>';
		printf(
			'<a href="%s" class="button button-primary">Approve</a>',
			esc_url( $this->verdict_link( $post->ID, 'yes' ) )
		);
		echo '&nbsp;';
		printf(
			'<a href="%s" class="button button-warning">Reject</a>',
			esc_url( $this->verdict_link( $post->ID, 'no' ) )
		);
		echo '</p>';

		echo '<p>';
		if ( count( $verdicts ) > 0 ) {
			printf(
				'<a href="%s" class="button button-primary">Finalize Verdict</a>',
				esc_url( $this->verdict_link( $post->ID, 'finalize' ) )
			);
		} else {
			echo '<button class="button" disabled>Finalize Verdict</button>';
		}
		echo '</p>';
	}

	public function render_metabox_photo( WP_Post $post ) {
		$image_url = $post->photo_img ?: $post->photo_url;
		$social_url = $post->photo_url;
		$input_label = 'Add photo';

		['ext' => $ext] = wp_check_filetype( $image_url, self::MIME_TYPES );

		if ( $ext !== false ) {
			printf(
				'<a href="%1$s" target="_blank"><img src="%1$s" width="100%%" class="app-photo" /></a>',
				esc_url( $image_url )
			);
			$input_label = 'Update photo';
		}

		if ( is_url( $social_url ) ) {
			$social_name = 'Social Link';
			if ( str_contains( $social_url, 'facebook.com' ) ) {
				$social_name = 'Facebook';
			} else if ( str_contains( $social_url, 'instagram.com' ) ) {
				$social_name = 'Instagram';
			}
			printf(
				'<p class="app-social"><a href="%s" target="_blank">%s</a></p>',
				esc_url( $social_url ),
				esc_html( $social_name )
			);
		} else if ( $social_url ) {
			printf( '<p class="app-social">%s</p>', esc_html( $social_url ) );
		} else {
			echo '<p><strong>Nothing provided!</strong></p>';
		}
		printf(
			'<p><label>%s: <input type="file" name="social_image" accept="%s" /></label></p>',
			esc_html( $input_label ),
			esc_attr( implode( ',', array_values( self::MIME_TYPES ) ) )
		);
		submit_button( 'Save image' );
	}

	public function remove_bulk_actions( array $actions, WP_Post $post ): array {
		if ( $post->post_type === self::POST_TYPE ) {
			return [];
		}
		return $actions;
	}

	public function hide_review_comments( array $args ): array {
		if ( empty( $args['type'] ) ) {
			$args['type'] = 'comment';
		}
		return $args;
	}

	public function update_messages( array $messages ): array {
		$messages[ self::POST_TYPE ] = [
			100 => 'Verdict added', // We're starting at 100 to avoid conflicts with posts.
			101 => 'Rejection sent',
			102 => 'Application approved',
			103 => 'Error creating new user',
		];
		return $messages;
	}

	private function finalize( WP_Post $post ): ?int {
		$verdicts = $this->get_unique_verdicts( $post->ID );
		$verdict_results = wp_list_pluck( $verdicts, 'approved' );
		$approved = count( $verdict_results ) === count( array_filter( $verdict_results ) );
		update_post_meta( $post->ID, 'status', $approved ? 'approved' : 'denied' );
		if ( ! $approved ) {
			Emails::instance()->send_email( $post->email, 'email_denied' );
			$post->post_status = self::STATUS_DENIED;
			wp_update_post( $post );
			return null;
		}

		$user_id = wp_insert_user( [
			'user_login' => $post->post_title,
			'user_nicename' => "{$post->post_title}-{$post->ID}",
			'user_pass' => wp_generate_password( 100 ),
			'user_email' => sanitize_email( $post->email ),
			'role' => 'customer',
			'meta_input' => [
				'pronouns' => $post->pronouns,
				'application' => $post->ID,
			],
		] );

		if ( is_wp_error( $user_id ) ) {
			log_error( $user_id );
			return 103;
		}
		$post->post_status = self::STATUS_APPROVED;
		wp_update_post( $post );
		update_post_meta( $post->ID, 'created_user', $user_id );
		Emails::instance()->send_user_email( $user_id, 'email_approved' );
		return null;
	}

	private function get_unique_verdicts( int $post_id ): array {
		$verdict_meta = get_post_meta( $post_id, 'verdict' );
		$verdicts = [];
		foreach ( $verdict_meta as $verdict_value ) {
			$user = get_userdata( absint( $verdict_value['user_id'] ?? 0 ) );
			if ( $user ) {
				$verdicts[ $user->ID ] = $verdict_value;
			}
		}
		return $verdicts;
	}

	private function verdict_link( int $post_id, string $verdict ): string {
		return add_query_arg( [
			'action' => 'verdict',
			'verdict' => $verdict,
			'_wpnonce' => wp_create_nonce( "verdict-{$post_id}" ),
		], get_edit_post_link( $post_id, 'url' ) );
	}
}
