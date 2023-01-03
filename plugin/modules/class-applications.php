<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Logger;
use Transgression\Modules\Email\Emailer;
use WP_Post;

use function Transgression\{insert_in_array, load_view};

class Applications extends Module {
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

	public function __construct( protected Emailer $emailer, protected Logger $logger ) {
		// Actions
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'save_post_' . self::POST_TYPE, [$this, 'save'] );
		add_action( 'post_action_verdict', [$this, 'action_verdict'] );

		// Display
		add_action( 'admin_enqueue_scripts', [$this, 'scripts'] );
		add_filter( 'post_updated_messages', [$this, 'update_messages'] );
		add_action( 'edit_form_top', [$this, 'render_status'] );
		add_action( 'post_edit_form_tag', function() { echo ' enctype="multipart/form-data"'; } );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'reviewed_column_header' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'reviewed_column' ], 10, 2 );
		add_filter( 'post_row_actions', [$this, 'remove_bulk_actions'], 10, 2 );
		add_filter( 'comments_list_table_query_args', [$this, 'hide_review_comments'] );

		// Email templates
		$emailer->add_template( 'app_approved', 'Application Approved' );
		$emailer->add_template( 'app_denied', 'Application Denied' );
		$emailer->add_template(
			'app_dupe',
			'Application Duplicate',
			'When someone submits an application but their email is already approved'
		);
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
				'create_posts' => 'do_not_allow',
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
		if ( $post->post_type !== self::POST_TYPE || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die();
		}

		$verdict = $_GET['verdict'];
		if ( $verdict === 'finalize' ) {
			$message = $this->finalize( $post );
		} else if ( $verdict === 'email' && $post->post_status !== 'pending' ) {
			$this->email_result( $post );
			$message = 104;
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
		if ( is_object( $screen ) && $screen->post_type === self::POST_TYPE ) {
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
		$fields = self::FIELDS;
		$original_field_keys = array_keys( $fields );
		if ( $post->_form_id ) {
			// $form_fields = get_form_fields_for_meta( absint( $post->_form_id ) );
			// $fields = array_merge( $form_fields, $fields );
		}
		load_view( 'applications/fields', compact( 'post', 'fields' ) );
	}

	public function render_metabox_comments( WP_Post $post ) {
		/** @var \WP_Comment[] */
		$comments = get_comments( [
			'post_id' => $post->ID,
			'order' => 'ASC',
			'type'=> self::COMMENT_TYPE,
		] );
		wp_list_comments( [ 'post_id' => $post->ID ] );
		load_view( 'applications/comments', compact( 'comments' ) );
	}

	public function render_metabox_verdict( WP_Post $post ) {
		$params = [
			'verdicts' => $this->get_unique_verdicts( $post->ID ),
			'finalized' => $post->post_status !== 'pending',
		];
		foreach ( [ 'yes', 'no', 'finalize', 'email' ] as $type ) {
			$params["{$type}_link"] = add_query_arg( [
				'action' => 'verdict',
				'verdict' => $type,
				'_wpnonce' => wp_create_nonce( "verdict-{$post->ID}" ),
			], get_edit_post_link( $post->ID, 'url' ) );
		}
		load_view( 'applications/verdicts', $params );
	}

	public function render_metabox_photo( WP_Post $post ) {
		$params = [
			'photo_url' => null,
			'input_label' => 'Add photo',
			'mime_types' => implode( ',', array_values( self::MIME_TYPES ) ),
			'social_url' => $post->photo_url,
			'social_label' => 'Social link',
		];

		$image_url = $post->photo_img ?: $post->photo_url;
		['ext' => $ext] = wp_check_filetype( $image_url, self::MIME_TYPES );
		if ( $ext !== false ) {
			$params['photo_url'] = $image_url;
			$params['input_label'] = 'Update photo';
		}

		if ( str_contains( $post->photo_url, 'facebook.com' ) ) {
			$params['social_label'] = 'Facebook';
		} else if ( str_contains( $post->photo_url, 'instagram.com' ) ) {
			$params['social_label'] = 'Instagram';
		}

		load_view( 'applications/photo', $params );
	}

	public function reviewed_column_header( array $columns ): array {
		return insert_in_array( $columns, [ 'reviewed' => 'Reviewed' ], 1 );
	}

	public function reviewed_column( string $column_name, int $post_id ): void {
		if ( 'reviewed' !== $column_name ) {
			return;
		}

		foreach ( $this->get_unique_verdicts( $post_id ) as $verdict ) {
			if ( $verdict['user_id'] === get_current_user_id() ) {
				echo $verdict['approved'] ? 'Approved' : 'Rejected';
				return;
			}
		}
		echo '<em>Not reviewed</em>';
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
		$verdict_message = sprintf(
			'Verdict added. <a href="%s">See all applications</a>.',
			admin_url( 'edit.php?post_type=' . self::POST_TYPE )
		);
		$messages[ self::POST_TYPE ] = [
			100 => $verdict_message, // We're starting at 100 to avoid conflicts with posts.
			101 => 'Rejection sent',
			102 => 'Application approved',
			103 => 'Error creating new user',
			104 => 'Emailed application results',
		];
		return $messages;
	}

	/**
	 * Gets a count of pending applications
	 *
	 * @return int
	 */
	public static function get_unreviewed_count(): int {
		$counts = wp_count_posts( self::POST_TYPE );
		return absint( $counts->pending ?? 0 );
	}

	/**
	 * Finalizes the application- either rejects or creates a new user, sending emails.
	 *
	 * @param WP_Post $post
	 * @return integer|null Error code to show in admin
	 */
	private function finalize( WP_Post $post ): ?int {
		$verdicts = $this->get_unique_verdicts( $post->ID );
		$verdict_results = wp_list_pluck( $verdicts, 'approved' );
		$approved = count( $verdict_results ) === count( array_filter( $verdict_results ) );
		update_post_meta( $post->ID, 'status', $approved ? 'approved' : 'denied' );
		if ( ! $approved ) {
			$post->post_status = self::STATUS_DENIED;
			wp_update_post( $post );
			$this->email_result( $post );
			return null;
		}

		$email = sanitize_email( $post->email );
		$image_url = $post->photo_img ?: $post->photo_url;
		$user_id = wp_insert_user( [
			'user_login' => $email,
			'user_pass' => wp_generate_password( 100 ),
			'user_email' => $email,
			'display_name' => $post->post_title,
			'role' => 'customer',
			'meta_input' => [
				'pronouns' => $post->pronouns,
				'application' => $post->ID,
				'image_url' => $image_url,
			],
		] );

		if ( is_wp_error( $user_id ) ) {
			$this->logger->error( $user_id );
			return 103;
		}
		$post->post_status = self::STATUS_APPROVED;
		wp_update_post( $post );
		update_post_meta( $post->ID, 'created_user', $user_id );
		$this->email_result( $post );
		return null;
	}

	private function email_result( WP_Post $post ): void {
		$status = $post->post_status;
		$template = null;
		if ( $status === self::STATUS_APPROVED ) {
			$template = 'app_approved';
		} else if ( $status === self::STATUS_DENIED ) {
			$template = 'app_denied';
		}

		if ( ! $template ) {
			return;
		}

		try {
			$email = $this->emailer
				->create( $post->email )
				->with_template( $template );
			$email->with_subject(
				// TODO: Allow subject customization
				$status === self::STATUS_APPROVED
					? 'You’re approved!'
					: 'Application denied'
			);
			if ( $post->created_user ) {
				$email->to_user( intval( $post->created_user ) );
			}
			$email->send();
		} catch ( \Throwable $err ) {
			$this->logger->error( $err );
		}
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
}
