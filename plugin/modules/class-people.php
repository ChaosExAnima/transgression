<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Logger;
use Transgression\Person;
use Transgression\Modules\Email\Emailer;
use WP_User;

use function Transgression\{get_current_url, load_view, prefix};

class People extends Module {
	public function __construct( protected Emailer $emailer, protected ForbiddenTickets $tickets ) {
		add_action( 'current_screen', [ $this, 'regen_code' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_widgets' ] );
		add_action( 'admin_notices', [ $this, 'show_application' ] );
		add_filter( 'manage_users_columns', [ $this, 'filter_admin_columns' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'filter_admin_column' ], 10, 3 );
		add_filter( 'user_row_actions', [ $this, 'filter_admin_row' ], 10, 2 );
		add_filter( 'user_contactmethods', [ $this, 'filter_contact_methods' ] );
	}

	/**
	 * Registers search widget
	 *
	 * @return void
	 */
	public function register_widgets() {
		if ( ! current_user_can( 'edit_apps' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			prefix( 'search' ),
			'Search People',
			[ $this, 'render_search_widget' ]
		);
		add_action( 'admin_footer', [ $this, 'render_search_widget_data' ] );
	}

	/**
	 * Renders the search dashboard widget
	 *
	 * @return void
	 */
	public function render_search_widget() {
		if ( ! current_user_can( 'edit_apps' ) ) {
			return;
		}
		$query = '';
		$people = [];
		if ( ! empty( $_GET['person_search'] ) && ! empty( $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			$query = sanitize_text_field( wp_unslash( $_GET['person_search'] ) );
			if ( $query && wp_verify_nonce( $nonce, prefix( 'person_search' ) ) ) {
				$people = Person::search( $query );
			}
		}

		load_view( 'person-widget', compact( 'query', 'people' ) );
	}

	/**
	 * Renders datalist for search widget
	 *
	 * @return void
	 */
	public function render_search_widget_data() {
		if ( ! current_user_can( 'edit_apps' ) ) {
			return;
		}
		$user_query = new \WP_User_Query( [ 'fields' => 'user_email' ] );
		echo '<datalist id="person-search-emails">';
		/** @var \WP_User $user */
		foreach ( $user_query->get_results() as $user_email ) {
			printf( '<option value="%s">', esc_attr( $user_email ) );
		}
		echo '</datalist>';
	}

	/**
	 * Shows a notice to a user's application, if any
	 *
	 * @return void
	 */
	public function show_application() {
		// @phpcs:ignore WordPress.Security.NonceVerification
		$regen_user = absint( $_GET['regen_success'] ?? '0' );
		if ( $regen_user ) {
			printf(
				'<div class="notice notice-success"><p>Successfully regenerated code for user %d.</p></div>',
				absint( $regen_user )
			);
		}
		global $user_id;
		if ( ! isset( $user_id ) ) {
			return;
		}
		$app_id = get_user_meta( $user_id, 'application', true );
		if ( ! $app_id ) {
			return;
		}
		$edit_link = get_edit_post_link( $app_id, 'url' );
		if ( ! $edit_link ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>See <a href="%s">application here</a>.</p></div>',
			esc_url( $edit_link )
		);
	}

	/**
	 * Adds pronouns to the contact methods
	 *
	 * @param array $methods
	 * @return array
	 */
	public function filter_contact_methods( array $methods ): array {
		$methods['pronouns'] = 'Pronouns';
		return $methods;
	}

	/**
	 * Removes posts column from users
	 * @param array $columns
	 * @return array
	 */
	public function filter_admin_columns( array $columns ): array {
		if ( isset( $columns['posts'] ) ) {
			unset( $columns['posts'] );
		}
		$columns['code'] = 'Code';
		return $columns;
	}

	/**
	 * Returns custom column markup
	 *
	 * @param string $html
	 * @param string $column_name
	 * @param integer $user_id
	 * @return string
	 */
	public function filter_admin_column( string $html, string $column_name, int $user_id ): string {
		if ( 'code' === $column_name ) {
			return sprintf(
				'<a href="%s" class="regen_code">%s (click to regen)</a>',
				esc_url( add_query_arg( [
					'regen_code' => $user_id,
					'_wpnonce' => wp_create_nonce( "regenerate_code_{$user_id}" ),
				], get_current_url() ) ),
				$this->tickets->get_code( $user_id )
			);
		}
		return $html;
	}

	/**
	 * Filters the admin to allow regenerating users
	 *
	 * @param array $actions
	 * @param WP_User $user
	 * @return array
	 */
	public function filter_admin_row( array $actions, WP_User $user ): array {
		unset( $actions['resetpassword'] );
		if ( $user->application ) {
			$actions['application'] = sprintf(
				'<a href="%s" class="application">View application</a>',
				esc_url( get_edit_post_link( $user->application ) )
			);
		}
		$actions['regen_code'] = sprintf(
			'<a href="%s" class="regen_code">Regenerate code</a>',
			esc_url( add_query_arg( [
				'regen_code' => $user->ID,
				'_wpnonce' => wp_create_nonce( "regenerate_code_{$user->ID}" ),
			], get_current_url() ) )
		);
		return $actions;
	}

	/**
	 * Regenerates a user's code
	 *
	 * @return void
	 */
	public function regen_code() {
		if ( ! isset( $_GET['regen_code'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen->id !== 'users' ) {
			return;
		}

		if ( ! current_user_can( 'edit_users' ) ) {
			$admin_id = get_current_user_id();
			Logger::error( "User {$admin_id} attempted to regenerate a code" );
			return;
		}

		$user_id = absint( $_GET['regen_code'] );
		check_admin_referer( "regenerate_code_{$user_id}" );

		$this->tickets->set_user_code( $user_id );
		$url = add_query_arg( [
			'regen_success' => $user_id,
			'paged' => absint( $_GET['paged'] ?? '' ),
		], admin_url( 'users.php' ) );
		wp_safe_redirect( $url );
	}
}
