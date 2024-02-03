<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Logger;
use Transgression\Modules\Email\Emailer;
use WP_Post;

use const Transgression\{PLUGIN_SLUG, PLUGIN_VERSION};

use function Transgression\{get_asset_url, insert_in_array, load_view};

class Applications extends Module {
	public const POST_TYPE = 'application';
	public const COMMENT_TYPE = 'review_comment';
	public const ROLE_REVIEWER = PLUGIN_SLUG . '_reviewer';

	public const STATUS_APPROVED = 'approved';
	public const STATUS_DENIED = 'denied';

	protected const LABELS = [
		'name' => 'Applications',
		'singular_name' => 'Application',
		'edit_item' => 'Review Application',
	];

	protected const FIELDS = [
		'post_title' => 'Name',
		'pronouns' => 'Pronouns',
		'email' => 'Email',
		'identity' => 'How they identify',
		'accessibility' => 'Accessibility concerns',
		'referrer' => 'How they know us',
		'associates' => 'Who they know',
		'conflicts' => 'Potential conflicts',
		'extra' => 'Additional comments',
	];

	protected const MIME_TYPES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png' => 'image/png',
		'tiff|tif' => 'image/tiff',
		'webp' => 'image/webp',
	];

	public function __construct(
		protected Conflicts $conflicts,
		protected Emailer $emailer
	) {
		parent::__construct();

		// Logic
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ] );
		add_action( 'post_action_verdict', [ $this, 'action_verdict' ] );
		add_filter( 'update_post_term_count_statuses', [ $this, 'filter_term_counts' ], 10, 2 );

		// Display
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'admin_head', [ $this, 'conflict_message' ] );
		add_filter( 'post_updated_messages', [ $this, 'update_messages' ] );
		add_action( 'edit_form_top', [ $this, 'render_status' ] );
		add_action(
			'post_edit_form_tag',
			function() {
				echo ' enctype="multipart/form-data"';
			}
		);
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'reviewed_column_header' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'reviewed_column' ], 10, 2 );
		add_filter( 'post_date_column_status', [ $this, 'date_state_column' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'remove_bulk_actions' ], 10, 2 );
		add_filter( 'comments_list_table_query_args', [ $this, 'hide_review_comments' ] );

		// Email templates
		$emailer->add_template( 'app_approved', 'Application Approved' );
		$emailer->add_template( 'app_denied', 'Application Denied' );
		$emailer->add_template(
			'app_dupe',
			'Application Duplicate',
			'When someone submits an application but their email is already approved'
		);
	}

	/**
	 * Initializes the post type and statuses
	 *
	 * @return void
	 */
	public function init() {
		$post_type = register_post_type( self::POST_TYPE, [
			'label' => self::LABELS['name'],
			'labels' => self::LABELS,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			'supports' => [ 'title', 'comments' ],
			'register_meta_box_cb' => [ $this, 'meta_boxes' ],
			'delete_with_user' => true,
			'capability_type' => [ 'app', 'apps' ],
			'capabilities' => [
				'create_posts' => 'do_not_allow',
			],
			'map_meta_cap' => true,
		] );

		register_post_status( self::STATUS_APPROVED, [
			'label' => 'Approved',
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			// translators: approved application count
			'label_count' => _n_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>',
				'transgression'
			),
		] );

		register_post_status( self::STATUS_DENIED, [
			'label' => 'Denied',
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			// translators: denied application count
			'label_count' => _n_noop(
				'Denied <span class="count">(%s)</span>',
				'Denied <span class="count">(%s)</span>',
				'transgression'
			),
		] );

		$app_caps = [];
		foreach ( array_keys( (array) $post_type->cap ) as $cap ) {
			$app_caps[ $cap ] = true;
		}

		add_role(
			self::ROLE_REVIEWER,
			'App Reviewer',
			[
				'read' => true,
				'view_admin_dashboard' => true, // This lets people see the back end
				...$app_caps,
			]
		);

		$role = get_role( 'administrator' );
		foreach ( array_keys( $app_caps ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Handles post saves
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function save( int $post_id ) {
		// New comments.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_comment = sanitize_textarea_field( wp_unslash( $_POST['newcomment'] ?? '' ) );
		if ( $new_comment ) {
			$user = wp_get_current_user();
			wp_insert_comment( [
				'comment_author' => $user->display_name,
				'comment_post_ID' => $post_id,
				'comment_content' => $new_comment,
				'comment_type' => self::COMMENT_TYPE,
			] );
		}

		$this->conflicts->check_conflict( get_post( $post_id ) );

		// Handle new image
		if ( empty( $_FILES['social_image'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security
		$new_image = $_FILES['social_image'];
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$new_file = wp_handle_upload( $new_image, [
			'test_form' => false,
			'mimes' => self::MIME_TYPES,
		] );

		if ( $new_file && ! isset( $new_file['error'] ) ) {
			update_post_meta( $post_id, 'photo_img', $new_file['url'] );
		}
	}

	public function action_verdict( int $post_id ) {
		check_admin_referer( "verdict-{$post_id}" );
		$post = get_post( $post_id );
		if ( $post->post_type !== self::POST_TYPE || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die();
		}

		// phpcs:ignore WordPress.Security
		$verdict = wp_unslash( $_GET['verdict'] );
		if ( $verdict === 'finalize' ) {
			$message = $this->finalize( $post );
		} elseif ( $verdict === 'email' && $post->post_status !== 'pending' ) {
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

	public function filter_term_counts( array $statuses, \WP_Taxonomy $taxonomy ): array {
		if ( $taxonomy->name === Conflicts::TAX_SLUG ) {
			return [ self::STATUS_APPROVED ];
		}
		return $statuses;
	}

	/**
	 * Enqueues the relevant styles
	 *
	 * @return void
	 */
	public function scripts() {
		$screen = get_current_screen();
		if (
			is_object( $screen ) &&
			$screen->post_type === self::POST_TYPE &&
			in_array( $screen->base, [ 'edit', 'post' ], true )
		) {
			wp_enqueue_style( PLUGIN_SLUG . '_application', get_asset_url( 'applications.css' ), [], PLUGIN_VERSION );
		}
	}

	public function render_status( WP_Post $post ) {
		if ( $post->post_type !== self::POST_TYPE ) {
			return;
		}

		$status = $post->post_status;
		if ( $status === 'approved' ) {
			$user = get_userdata( $post->created_user );
			if ( ! $user ) {
				echo '<div class="notice notice-warning"><p>Application for missing user!</p></div>';
				return;
			}
			printf(
				'<div class="notice notice-success"><p>%1$s <a href="%3$s">%2$s</a></p></div>',
				'Application is approved for',
				esc_html( $user->display_name ),
				esc_url( get_edit_user_link( $user->ID ) )
			);
		} elseif ( $status === 'denied' ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				'This application is denied'
			);
		}
	}

	public function meta_boxes( WP_Post $application ) {
		add_meta_box(
			self::POST_TYPE . '_fields',
			'Form Entry',
			[ $this, 'render_metabox_fields' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
		if ( count( $this->conflicts->get_for_app( $application ) ) > 0 ) {
			add_meta_box(
				self::POST_TYPE . '_conflicts',
				'Conflicts',
				[ $this, 'render_metabox_conflicts' ],
				self::POST_TYPE,
				'normal',
				'high'
			);
		}
		add_meta_box(
			self::POST_TYPE . '_comments',
			'Comments',
			[ $this, 'render_metabox_comments' ],
			self::POST_TYPE,
			'normal'
		);
		add_meta_box(
			self::POST_TYPE . '_verdict',
			'Verdict',
			[ $this, 'render_metabox_verdict' ],
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			self::POST_TYPE . '_photo',
			'Photo',
			[ $this, 'render_metabox_photo' ],
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

		load_view( 'applications/fields', compact( 'post', 'fields' ) );
	}

	public function render_metabox_conflicts( WP_Post $post ) {
		$conflicts = $this->conflicts->get_for_app( $post );
		$checked = $this->conflicts->get_app_checked_ids( $post );

		load_view( 'applications/conflicts', compact( 'conflicts', 'checked' ) );
	}

	public function render_metabox_comments( WP_Post $post ) {
		/** @var \WP_Comment[] */
		$comments = get_comments( [
			'post_id' => $post->ID,
			'order' => 'ASC',
			'type' => self::COMMENT_TYPE,
		] );
		load_view( 'applications/comments', compact( 'comments' ) );
	}

	public function render_metabox_verdict( WP_Post $post ) {
		$params = [
			'verdicts' => $this->get_unique_verdicts( $post->ID ),
			'finalized' => $post->post_status !== 'pending',
		];
		foreach ( [ 'yes', 'no', 'finalize', 'email' ] as $type ) {
			$params[ "{$type}_link" ] = add_query_arg( [
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
		[ 'ext' => $ext ] = wp_check_filetype( $image_url, self::MIME_TYPES );
		if ( $ext !== false ) {
			$params['photo_url'] = $image_url;
			$params['input_label'] = 'Update photo';
		}

		if ( str_contains( $post->photo_url, 'facebook.com' ) ) {
			$params['social_label'] = 'Facebook';
		} elseif ( str_contains( $post->photo_url, 'instagram.com' ) ) {
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

	public function date_state_column( string $status, WP_Post $post ): string {
		if ( $post->post_type === self::POST_TYPE ) {
			return esc_html__( 'Submitted', 'transgression' );
		}
		return $status;
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

	/**
	 * Shows a warning if conflicts are found.
	 *
	 * @return void
	 */
	public function conflict_message(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== self::POST_TYPE ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$conflicts = wp_get_post_terms( $post->ID, Conflicts::TAX_SLUG );
		if ( is_array( $conflicts ) && count( $conflicts ) > 0 ) {
			wp_admin_notice( 'Conflicts found, please resolve them before adding a verdict!', [ 'type' => 'warning' ] );
		}
	}

	/**
	 * Add messages for applications.
	 *
	 * @param array $messages Post messages array.
	 * @return array
	 */
	public function update_messages( array $messages ): array {
		$app_link = sprintf(
			'<a href="%s">See all applications</a>.',
			admin_url( 'edit.php?post_type=' . self::POST_TYPE )
		);
		// We're starting at 100 to avoid conflicts with posts.
		$messages[ self::POST_TYPE ] = [
			100 => "Verdict added. {$app_link}",
			101 => 'Application rejected',
			102 => "Application approved. {$app_link}",
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
		$name = trim( $post->post_title );
		$name_parts = explode( ' ', $name, 2 );
		$user_meta = [
			'nickname' => $name,
			'first_name' => $name_parts[0] ?? $name,
			'pronouns' => $post->pronouns,
			'application' => $post->ID,
			'image_url' => $post->photo_img ?: $post->photo_url,
		];
		if ( count( $name_parts ) === 2 ) {
			$user_meta['last_name'] = $name_parts[1];
		}
		if ( $post->user_extra ) {
			$user_meta['app_extra'] = $post->user_extra;
		}
		$user_id = wp_insert_user( [
			'role' => 'customer',
			'user_pass' => wp_generate_password( 100 ),
			'user_login' => $email,
			'user_email' => $email,
			'display_name' => $name,
			'meta_input' => $user_meta,
		] );

		if ( is_wp_error( $user_id ) ) {
			Logger::error( $user_id );
			return 103;
		}
		$post->post_status = self::STATUS_APPROVED;
		wp_update_post( $post );
		update_post_meta( $post->ID, 'created_user', $user_id );
		$this->email_result( $post );
		return null;
	}

	/**
	 * Emails the results of the application
	 *
	 * @param WP_Post $post
	 * @return void
	 */
	private function email_result( WP_Post $post ): void {
		$status = $post->post_status;
		$template = null;
		if ( $status === self::STATUS_APPROVED ) {
			$template = 'app_approved';
		} elseif ( $status === self::STATUS_DENIED ) {
			$template = 'app_denied';
		}

		if ( ! $template || ! $this->emailer->is_template( $template ) ) {
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
			Logger::error( $err );
		}
	}

	/**
	 * Gets the most recent verdicts for a given application ID
	 *
	 * @param int $post_id
	 * @return array
	 */
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
