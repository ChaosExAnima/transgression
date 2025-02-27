<?php declare( strict_types = 1 );

namespace Transgression\Modules;

use stdClass;
use Transgression\Admin\Page;
use Transgression\Admin\Page_Options;
use WP_Post;
use WP_Query;

use function Transgression\load_view;

use const Transgression\PLUGIN_SLUG;

class Conflicts extends Module {
	public const COMMENT_TYPE = 'conflict_comment';
	public const CACHE_KEY = PLUGIN_SLUG . '_conflict_ids';
	public const OPTION_SHEET_URL = 'conflict_sheet_url';

	protected Page $admin;

	public function __construct( protected Page_Options $settings ) {
		add_action( 'pending_to_' . Applications::STATUS_APPROVED, [ $this, 'bust_cache' ] );

		$admin = new Page( 'conflicts', 'Conflicts', null, 'edit_posts' );
		$admin->as_post_subpage( Applications::POST_TYPE );
		$admin->add_screen_callback( [ $this, 'action_redirect' ] );
		$admin->add_render_callback( [ $this, 'render_conflicts' ] );
		$admin->add_style( 'conflicts' );

		$admin->add_action( 'resolve', [ $this, 'resolve_conflict' ] );
		$admin->register_message( 'resolve_error', 'Could not hide conflict' );
		$admin->register_message( 'resolve_success', 'Hid conflict', 'success' );

		$admin->add_action( 'comment', [ $this, 'add_comment' ] );
		$admin->register_message( 'comment_error', 'Could not add comment' );
		$admin->register_message( 'comment_success', 'Added comment', 'success' );

		$this->admin = $admin;
	}

	/**
	 * Redirects to the conflict sheet if set.
	 *
	 * @return void
	 */
	public function action_redirect(): void {
		$redirect = $this->settings->value( self::OPTION_SHEET_URL );
		// @phpcs:ignore WordPress.Security.SafeRedirect
		if ( $redirect && wp_redirect( $redirect ) ) {
			exit;
		}
	}

	/**
	 * Renders conflicts
	 *
	 * @return void
	 */
	public function render_conflicts(): void {
		$post_ids = wp_cache_get( self::CACHE_KEY );
		if ( $post_ids === false ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$post_ids = $wpdb->get_col(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'conflicts' AND CHAR_LENGTH(meta_value) > 4"
			);
			wp_cache_set( self::CACHE_KEY, $post_ids );
		}

		$query = new WP_Query( [
			'post_type' => Applications::POST_TYPE,
			'post_status' => Applications::STATUS_APPROVED,
			'posts_per_page' => -1,
			'post__in' => $post_ids,
		] );
		$current_url = add_query_arg( [
			'post_type' => Applications::POST_TYPE,
			'page' => PLUGIN_SLUG . '_conflicts',
		], admin_url( 'edit.php' ) );

		load_view( 'conflicts/table', compact( 'current_url', 'query' ) );
	}

	/**
	 * Busts the conflict cache when an application is approved
	 *
	 * @param WP_Post $post The application
	 * @return void
	 */
	public function bust_cache( WP_Post $post ): void {
		if ( Applications::POST_TYPE === $post->post_type ) {
			wp_cache_delete( self::CACHE_KEY );
		}
	}

	/**
	 * Resolves a given conflict for an application
	 *
	 * @param string $application_id
	 * @return void
	 */
	public function resolve_conflict( string $application_id ) {
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
		check_admin_referer( "comment-{$app_id}" );

		if ( ! $comment || ! $app_id ) {
			wp_safe_redirect( $this->admin->get_url() );
			exit;
		}

		$app = get_post( $app_id );
		if (
			! $app ||
			Applications::POST_TYPE !== $app->post_type ||
			Applications::STATUS_APPROVED !== $app->post_status
		) {
			wp_die( 'Application invalid' );
		}

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
}
