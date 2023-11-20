<?php declare( strict_types = 1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use Transgression\Logger;
use WP_Post;
use WP_Query;

use function Transgression\get_safe_post;
use function Transgression\load_view;

use const Transgression\PLUGIN_SLUG;

class Conflicts extends Module {
	public const TAX_SLUG = PLUGIN_SLUG . '_conflict';
	public const CAP_EDIT = 'edit_apps';
	public const COMMENT_TYPE = 'conflict_comment';
	public const IDS_CACHE_KEY = PLUGIN_SLUG . '_conflict_ids';
	public const FLAGS_CACHE_KEY = PLUGIN_SLUG . '_confict_flags';

	protected Page $admin;

	public function __construct( protected Logger $logger ) {
		parent::__construct();
		add_action( 'pending_to_' . Applications::STATUS_APPROVED, [ $this, 'bust_cache' ] );

		$admin = new Page( 'conflicts', 'Conflicts', null, 'edit_posts' );
		$admin->as_post_subpage( Applications::POST_TYPE );
		$admin->add_render_callback( [ $this, 'render_conflicts' ] );
		$admin->add_style( 'conflicts' );

		$admin->add_action( 'resolve', [ $this, 'action_resolve' ] );
		$admin->register_message( 'resolve_error', 'Could not hide conflict' );
		$admin->register_message( 'resolve_success', 'Hid conflict', 'success' );

		$admin->add_action( 'comment', [ $this, 'add_comment' ] );
		$admin->register_message( 'comment_error', 'Could not add comment' );
		$admin->register_message( 'comment_success', 'Added comment', 'success' );

		$admin->add_action( 'flag', [ $this, 'add_flag' ] );
		$admin->register_message( 'flag_error', 'Could not add flag' );
		$admin->register_message( 'flag_success', 'Added flag', 'success' );

		$this->admin = $admin;
	}

	/**
	 * Registers taxonomy
	 * @return void
	 */
	public function init(): void {
		register_taxonomy(
			self::TAX_SLUG,
			Applications::POST_TYPE,
			[
				'labels' => [
					'name' => 'Conflicts',
					'singular_name' => 'Conflict',
					'search_items' => 'Search Conflicts',
					'all_items' => 'All Conflicts',
					'edit_item' => 'Edit Conflict',
					'update_item' => 'Update Conflict',
				],
				'public' => false,
				'capabilities' => [
					'manage_terms' => self::CAP_EDIT,
					'edit_terms' => self::CAP_EDIT,
					'delete_terms' => self::CAP_EDIT,
					'assign_terms' => self::CAP_EDIT,
				],
			]
		);

		$this->admin->add_script( 'conflicts' );
	}

	/**
	 * Renders conflicts
	 *
	 * @return void
	 */
	public function render_conflicts(): void {
		$post_ids = wp_cache_get( self::IDS_CACHE_KEY );
		if ( $post_ids === false ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$post_ids = $wpdb->get_col(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'conflicts' AND CHAR_LENGTH(meta_value) > 4"
			);
			wp_cache_set( self::IDS_CACHE_KEY, $post_ids );
		}

		$query = new WP_Query( [
			'post_type' => Applications::POST_TYPE,
			'post_status' => Applications::STATUS_APPROVED,
			'posts_per_page' => -1,
			'post__in' => $post_ids,
		] );

		load_view( 'conflicts/table', [
			'query' => $query,
			'admin' => $this->admin,
		]  );
	}

	/**
	 * Busts the conflict cache when an application is approved
	 *
	 * @param WP_Post $post The application
	 * @return void
	 */
	public function bust_cache( WP_Post $post ): void {
		if ( Applications::POST_TYPE === $post->post_type ) {
			wp_cache_delete( self::IDS_CACHE_KEY );
		}
	}

	/**
	 * Resolves a given conflict for an application
	 *
	 * @param string $application_id
	 * @return void
	 */
	public function action_resolve( string $application_id ) {
		check_admin_referer( "conflict-{$application_id}" );

		$app = get_post( absint( $application_id ) );
		if ( ! $app ) {
			$this->admin->redirect_message( 'resolve_error' );
		}
		update_post_meta( $app->ID, 'hide_conflict', true );
		$this->admin->redirect_message( 'resolve_success' );
	}

	/**
	 * Adds a comment to a specific application
	 *
	 * @param string $comment
	 * @return void
	 */
	public function add_comment( string $comment ): void {
		$app_id = absint( $_GET['app_id'] ?? null );
		check_admin_referer( "conflict-{$app_id}" );

		if ( ! $comment || ! $app_id ) {
			wp_safe_redirect( $this->admin->get_url() );
			exit;
		}

		$app = $this->get_action_app( $app_id );
		$user = wp_get_current_user();
		if ( ! user_can( $user, 'edit_posts' ) ) {
			wp_die( 'Cannot leave comments' );
		}
		$result = wp_insert_comment( [
			'comment_author' => $user->display_name,
			'comment_post_ID' => $app_id,
			'comment_content' => $comment,
			'comment_type' => self::COMMENT_TYPE,
			'user_id' => $user->ID,
		] );
		if ( ! $result ) {
			$this->admin->redirect_message( 'comment_error' );
		}
		$this->admin->redirect_message( 'comment_success' );
	}

	/**
	 * Adds a new flag to an application
	 *
	 * @param string $name
	 * @return void
	 */
	public function add_flag( string $app_id ): void {
		// Validation
		$app_id = absint( $app_id );
		check_admin_referer( "conflict-{$app_id}" );
		if ( $app_id ) {
			$this->get_action_app( $app_id );
		}
		$name = get_safe_post( 'name' );
		if ( ! $name ) {
			$this->admin->redirect_message( 'flag_error' );
		}

		// Create flag name
		$result = wp_insert_term(
			$name,
			self::TAX_SLUG,
		);
		$this->handle_flag_error( $result );
		wp_cache_delete( self::FLAGS_CACHE_KEY );
		$term_id = $result['term_id'];

		// Term meta
		$email = get_safe_post( 'email' );
		if ( is_email( $email ) ) {
			add_term_meta(
				$term_id,
				'email',
				$email,
				true
			);
		}
		$instagram = get_safe_post( 'instagram' );
		if ( $instagram ) {
			add_term_meta(
				$term_id,
				'instagram',
				$instagram,
				true
			);
		}

		// App data
		if ( $app_id ) {
			$meta_id = add_term_meta(
				$term_id,
				'source',
				$app_id,
				true
			);
			$this->handle_flag_error( $meta_id );

			// Then add to the application that created it
			$result = wp_add_object_terms(
				$app_id,
				$term_id,
				self::TAX_SLUG
			);
			$this->handle_flag_error( $result );
		}

		// Success!
		$this->admin->redirect_message( 'flag_success' );
	}

	protected function handle_flag_error( mixed $result ): void {
		if ( is_wp_error( $result ) ) {
			$this->logger->error( $result );
			$this->admin->redirect_message( 'flag_error' );
		}
	}

	protected function get_flags(): array {
		$cached = wp_cache_get( self::FLAGS_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms( [
			'taxonomy' => self::TAX_SLUG,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $terms ) ) {
			return [];
		}
		$flags = [];
		foreach ( $terms as $term ) {
			$flags[] = [
				'id' => $term->term_id,
				'source_id' => $term->source,
				'name' => $term->name,
				'email' => $term->email,
				'instagram' => $term->instagram,
			];
		}
		wp_cache_set( self::FLAGS_CACHE_KEY, $flags, '', DAY_IN_SECONDS );
		return $flags;
	}

	/**
	 * Gets the app for an action
	 *
	 * @param integer $app_id
	 * @return \WP_Post
	 */
	protected function get_action_app( int $app_id ): \WP_Post {
		$app = get_post( $app_id );
		if (
			! $app ||
			Applications::POST_TYPE !== $app->post_type ||
			Applications::STATUS_APPROVED !== $app->post_status
		) {
			wp_die( 'Application invalid' );
		}
		return $app;
	}
}
