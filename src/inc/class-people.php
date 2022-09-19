<?php declare( strict_types=1 );

namespace Transgression;

use WP_Post;
use WP_User;

class People extends Helpers\Singleton {
	protected function __construct() {

		// Logging in
		add_action( 'template_redirect', [ $this, 'handle_login' ] );
		add_action( 'template_redirect', [ $this, 'redirect_to_profile' ] );
		add_filter( 'login_message', [ $this, 'filter_login_message' ] );

		// Account
		add_filter( 'woocommerce_save_account_details_required_fields', [ $this, 'filter_fields' ] );
		add_action( 'woocommerce_save_account_details', [ $this, 'save_pronouns' ] );

		// Admin
		add_action( 'admin_action_regen_avatar', [ $this, 'trigger_regen' ] );
		add_filter( 'pre_get_avatar_data', [ $this, 'filter_avatar' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'show_application' ] );
		add_filter( 'user_row_actions', [ $this, 'filter_admin_row' ], 10, 2 );
		add_filter( 'user_contactmethods', [ $this, 'filter_contact_methods' ] );
		add_filter( 'user_profile_picture_description', [ $this, 'filter_avatar_description' ], 10, 2 );

		// Misc
		add_action( 'trans_cron_user_avatar', [ $this, 'regenerate_avatar' ] );
	}

	public function handle_login() {
		// Skip for logged in users.
		if ( is_user_logged_in() ) {
			return;
		}

		$current_url = strip_query( get_current_url() );

		// Try logging in?
		if ( $this->check_login( get_current_url() ) ) {
			$user_id = email_exists( $_GET['email'] );
			wp_set_auth_cookie( $user_id );
			wc_add_notice( sprintf(
				'You are now logged in, %s. <a href="%s">Log out</a>',
				esc_html( get_userdata( $user_id )->display_name ),
				esc_url( wp_logout_url( $current_url ) )
			) );
			wp_safe_redirect( $current_url );
			exit;
		}

		if ( empty( $_POST['login-email'] ) || is_admin() ) {
			return;
		}

		$email = sanitize_email( $_POST['login-email'] );
		$user_id = email_exists( $email );
		if ( is_integer( $user_id ) ) {
			$this->send_login_email( $user_id );
		}

		// Set the session cookie so notices work.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		wc_add_notice( sprintf(
			'Check your email %s for a login link. If you don&rsquo;t see it, ' .
			'<a href="%s" target="_blank">contact us</a>.',
			esc_html( $email ),
			esc_url( 'mailto:' . get_option( 'admin_email' ) . '?subject=Login Issues' )
		) );
		wp_safe_redirect( $current_url );
		exit;
	}

	public function redirect_to_profile() {
		if ( !is_edit_account_page() || !is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $this->is_passwordless( $user_id ) ) {
			return;
		}
		wp_safe_redirect( get_edit_profile_url( $user_id ) );
		exit;
	}

	public function filter_login_message( string $message ): string {
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'purchase' ) {
			$message .= '<p class="message">You need to log in to buy tickets</p>';
		}
		return $message;
	}

	public function filter_fields( array $fields ): array {
		unset( $fields['account_first_name'] );
		unset( $fields['account_last_name'] );
		return $fields;
	}

	public function save_pronouns( int $user_id ) {
		$new_pronouns = trim( sanitize_text_field( $_POST['account_pronouns'] ) );
		if ( $new_pronouns === '' ) {
			delete_user_meta( $user_id, 'pronouns' );
		} else {
			update_user_meta( $user_id, 'pronouns', $new_pronouns );
		}
	}

	/**
	 * Shows a notice to a user's application, if any
	 *
	 * @return void
	 */
	public function show_application() {
		if ( isset( $_GET['update'] ) && $_GET['update'] === 'regenavatar' ) {
			echo '<div class="notice notice-success"><p>Started regenerating avatar</p></div>';
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
		if ( !$edit_link ) {
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
	 * Shows a link to regenerate the avatar, if available
	 *
	 * @param string $description
	 * @param WP_User $user
	 * @return string
	 */
	public function filter_avatar_description( string $description, WP_User $user ): string {
		if ( $description ) {
			return $description;
		}

		if ( $user->application ) {
			$url = add_query_arg( [
				'action' => 'regen_avatar',
				'user_id' => $user->ID,
			] );
			return sprintf(
				'<a href="%s">Update the avatar from the application</a>',
				esc_url( wp_nonce_url( $url, "regenavatar-{$user->ID}" ) )
			);
		}
		return '';
	}

	/**
	 * Filters the avatar function to get the application photo
	 *
	 * @param array $args
	 * @param mixed $id_or_email
	 * @return array
	 */
	public function filter_avatar( array $args, mixed $id_or_email ): array {
		if ( is_int( $id_or_email ) ) {
			$user = get_user_by( 'id', $id_or_email );
		} else if ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		}
		if ( empty( $user ) ) {
			return $args;
		}

		if ( $user->avatar ) {
			$image = wp_get_attachment_image_src( $user->avatar, [ $args['width'], $args['height'] ] );
			if ( ! $image ) {
				return $args;
			}
			$args['url'] = $image[0];
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Filters the admin to allow regenerating users
	 *
	 * @param array $actions
	 * @param WP_User $user
	 * @return array
	 */
	public function filter_admin_row( array $actions, WP_User $user ): array {
		if ( current_user_can( 'edit_user', $user->ID ) && $user->application ) {
			$url = add_query_arg( [
				'action' => 'regen_avatar',
				'user_id' => $user->ID,
			], admin_url( 'users.php' ) );
			if ( isset( $_GET['paged'] ) ) {
				$url = add_query_arg( 'paged', absint( $_GET['paged'] ), $url );
			}
			$actions['regenavatar'] = sprintf(
				'<a href="%s">Regen Avatar</a>',
				esc_url( wp_nonce_url( $url, "regenavatar-{$user->ID}" ) )
			);
		}

		if ( false !== array_search( 'customer', $user->roles, true ) ) {
			unset( $actions['resetpassword'] );
		}
		return $actions;
	}

	/**
	 * Triggers an avatar regeneration
	 *
	 * @return void
	 */
	public function trigger_regen(): void {
		if ( ! isset( $_GET['user_id'] ) ) {
			return;
		}
		$user_id = absint( $_GET['user_id'] );
		check_admin_referer( "regenavatar-{$user_id}" );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( 'You are not allowed to edit this user' );
		}

		// Do the regen!
		wp_schedule_single_event( time(), 'trans_cron_user_avatar', [ $user_id ] );

		// Redirect back to the right place
		$redirect = admin_url( 'users.php' );
		if ( str_contains( $_SERVER['REQUEST_URI'], 'user-edit.php' ) ) {
			$redirect = add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );
		} else if ( isset( $_GET['paged'] ) ) {
			$redirect = add_query_arg( 'paged', absint( $_GET['paged'] ), $redirect );
		}
		$redirect = add_query_arg( 'update', 'regenavatar', $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Regenerates a user's avatar from an application
	 *
	 * @param int $user_id
	 * @return void
	 */
	public function regenerate_avatar( int $user_id ): void {
		log_error( "Trying to regen avatar for user {$user_id}" );
		// Get the user and application post
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! $user->application ) {
			return;
		}
		/** @var int */
		$app_id = $user->application;
		/** @var WP_Post */
		$app = get_post( $app_id );
		if ( ! $app ) {
			return;
		}
		$avatar_id = Applications::load_application_image( $app );
		if ( $avatar_id ) {
			update_user_meta( $user_id, 'avatar', $avatar_id );
			log_error( "Successfully set avatar for {$user_id} to image {$avatar_id}" );
		}
	}

	/** Private methods */

	private function send_login_email( int $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( !$user ) {
			log_error( "Tried to log in with user ID of {$user_id}" );
			return;
		}

		$this->redirect_to_login_if_not_customer( $user );

		// Generate a random value.
		$key = "user_login_{$user->user_email}";
		$token = wp_generate_password( 20, false );
		set_transient( $key, $token, MINUTE_IN_SECONDS * 5 );

		// Build a URL.
		$args = [
			'login' => 'purchase',
			'email' => rawurlencode( $user->user_email ),
			'token' => $token,
			'nonce' => wp_create_nonce( $key ),
		];
		$url = add_query_arg( $args, get_current_url() );
		$hash = wp_hash( $url );
		$login_url = add_query_arg( 'hash', $hash, $url );

		// Send the email.
		$emails = Emails::instance();
		$emails->set_custom_url( $login_url );
		$emails->send_user_email( $user_id, 'email_login' );
	}

	private function check_login( string $url ): bool {
		// First, is this even valid?
		if (
			empty( $_GET['login'] ) ||
			$_GET['login'] !== 'purchase' ||
			empty( $_GET['email'] ) ||
			empty( $_GET['token'] )
		) {
			return false;
		}

		// Verify the hash so we know it wasn't tampered with.
		$email = sanitize_email( $_GET['email'] );
		$hash = wp_hash( remove_query_arg( 'hash', $url ) );
		if ( empty( $_GET['hash'] ) || ! hash_equals( $hash, $_GET['hash'] ) ) {
			log_error( "Login hash check failed for {$email}" );
			return false;
		}

		// Check the nonce.
		$key = "user_login_{$email}";
		if ( wp_verify_nonce( $_GET['nonce'], $key ) !== 1 ) {
			log_error( "Login nonce check failed for {$email}" );
			return false;
		}

		// Does this user exist?
		if ( !email_exists( $email ) ) {
			log_error( "Login email check failed for {$email}" );
			return false;
		}

		// Finally, check the token.
		$token = get_transient( $key );
		if ( false === $token ) {
			return false;
		}
		return hash_equals( $token, $_GET['token'] );
	}

	private function redirect_to_login_if_not_customer( WP_User $user ) {
		if ( !$this->is_passwordless( $user->ID ) ) {
			$url = add_query_arg( 'action', 'purchase', wp_login_url( get_current_url() ) );
			wp_safe_redirect( $url );
			exit;
		}
	}

	private function is_passwordless( int $user_id ): bool {
		return wc_user_has_role( $user_id, 'customer' );
	}
}
