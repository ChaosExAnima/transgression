<?php declare( strict_types = 1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use WP_Post;
use WP_Query;

use function Transgression\load_view;

use const Transgression\PLUGIN_SLUG;

class Conflicts extends Module {
	public const COMMENT_TYPE = PLUGIN_SLUG . '_conflict';
	public const CACHE_KEY = PLUGIN_SLUG . '_conflict_ids';

	protected Page $page;

	public function __construct() {
		add_action( 'pending_to_' . Applications::STATUS_APPROVED, [ $this, 'bust_cache' ] );

		$page = new Page( 'conflicts', 'Conflicts', null, 'edit_posts' );
		$page->as_post_subpage( Applications::POST_TYPE );
		$page->add_render_callback( [ $this, 'render_conflicts' ] );

		$page->add_action( 'resolve', [ $this, 'resolve_conflict' ] );
		$page->register_message( 'resolve_error', 'Could not hide conflict' );
		$page->register_message( 'resolve_success', 'Hid conflict', 'success' );

		$page->add_action( 'comment', [ $this, 'add_comment' ] );
		$page->register_message( 'add_comment', 'Added comment' );

		$page->add_style( 'conflicts' );

		$this->page = $page;
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
				WHERE meta_key = 'warnings' AND CHAR_LENGTH(meta_value) > 4"
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
			$this->page->redirect_message( 'resolve_error' );
		}
		update_post_meta( $app->ID, 'hide_conflict', true );
		$this->page->redirect_message( 'resolve_success' );
	}

	/**
	 * Adds a comment to a specific application
	 *
	 * @param string $comment
	 * @return void
	 */
	public function add_comment( string $comment ): void {
	}
}
