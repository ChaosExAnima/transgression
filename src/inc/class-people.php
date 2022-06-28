<?php declare( strict_types=1 );

namespace Transgression;

use Error;
use WC_Product;
use WP_User;

class People extends Singleton {
	protected function __construct() {

		// Logging in
		add_action( 'template_redirect', [ $this, 'handle_login' ] );
		add_action( 'template_redirect', [ $this, 'redirect_to_profile' ] );
		add_filter( 'login_message', [ $this, 'filter_login_message' ] );

		// Account
		add_filter( 'woocommerce_save_account_details_required_fields', [ $this, 'filter_fields' ] );
		add_action( 'woocommerce_save_account_details', [ $this, 'save_pronouns' ] );
		add_filter( 'user_contactmethods', [ $this, 'filter_contact_methods' ] );
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

	public function filter_contact_methods( array $methods ): array {
		$methods['pronouns'] = 'Pronouns';
		return $methods;
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
