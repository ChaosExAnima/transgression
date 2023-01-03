<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Logger;
use Transgression\Modules\Email\Emailer;
use WP_User;

use function Transgression\{get_current_url, strip_query};

class People extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = ['woocommerce/woocommerce.php'];

	public function __construct( protected Emailer $emailer, protected Logger $logger ) {
		if ( ! self::check_plugins() ) {
			return;
		}

		// Logging in
		add_action( 'template_redirect', [ $this, 'handle_login' ] );
		add_action( 'template_redirect', [ $this, 'redirect_to_profile' ] );
		add_filter( 'login_message', [ $this, 'filter_login_message' ] );

		// Account
		add_filter( 'woocommerce_save_account_details_required_fields', [ $this, 'filter_fields' ] );
		add_action( 'woocommerce_save_account_details', [ $this, 'save_pronouns' ] );

		// Admin
		add_action( 'admin_notices', [ $this, 'show_application' ] );
		add_filter( 'user_row_actions', [ $this, 'filter_admin_row' ], 10, 2 );
		add_filter( 'user_contactmethods', [ $this, 'filter_contact_methods' ] );

		// Email templates
		$emailer->add_template(
			'people_login',
			'Login Email',
			'Use tag <code>[login-url]text[/login-url]</code> for special login link'
		);
	}

	/**
	 * Handles logging in
	 *
	 * @return void
	 */
	public function handle_login() {
		// Skip for logged in users.
		if ( is_user_logged_in() ) {
			return;
		}

		$current_url = strip_query( get_current_url() );

		// Try logging in?
		if ( $this->check_login( get_current_url() ) ) {
			$user_id = email_exists( $_GET['email'] );
			$user = get_userdata( $user_id );
			$this->redirect_to_login_if_not_customer( $user );

			wp_set_auth_cookie( $user_id );
			wc_add_notice( sprintf(
				'You are now logged in, %s. <a href="%s">Log out</a>',
				esc_html( $user->display_name ),
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

	/**
	 * Filters login message for Woo
	 *
	 * @param string $message
	 * @return string
	 */
	public function filter_login_message( string $message ): string {
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'purchase' ) {
			$message .= '<p class="message">You need to log in to buy tickets</p>';
		}
		return $message;
	}

	/**
	 * Filters out fields for Woo
	 *
	 * @param array $fields
	 * @return array
	 */
	public function filter_fields( array $fields ): array {
		unset( $fields['account_first_name'] );
		unset( $fields['account_last_name'] );
		return $fields;
	}

	/**
	 * Saves pronouns for a user
	 *
	 * @param integer $user_id
	 * @return void
	 */
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
	 * Filters the admin to allow regenerating users
	 *
	 * @param array $actions
	 * @param WP_User $user
	 * @return array
	 */
	public function filter_admin_row( array $actions, WP_User $user ): array {
		if ( $this->is_passwordless( $user->ID ) ) {
			unset( $actions['resetpassword'] );
		}
		return $actions;
	}

	/** Private methods */

	/**
	 * Sends a login email
	 *
	 * @param integer $user_id
	 * @return void
	 */
	private function send_login_email( int $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( !$user ) {
			$this->logger->error( "Tried to log in with user ID of {$user_id}" );
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
		$email = $this->emailer->create();
		$email
			->to_user( $user_id )
			->with_template( 'email_login' )
			->set_url( 'login-url', $login_url )
			->send();
	}

	/**
	 * Checks a login URL
	 *
	 * @param string $url
	 * @return boolean
	 */
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
			$this->logger->log( "Login hash check failed for {$email}" );
			return false;
		}

		// Check the nonce.
		$key = "user_login_{$email}";
		if ( wp_verify_nonce( $_GET['nonce'], $key ) !== 1 ) {
			$this->logger->log( "Login nonce check failed for {$email}" );
			return false;
		}

		// Does this user exist?
		$user_id = email_exists( $email );
		if ( ! $user_id ) {
			$this->logger->log( "Login email check failed for {$email}" );
			return false;
		}

		// Check the token.
		$token = get_transient( $key );
		if ( false === $token || ! hash_equals( $token, $_GET['token'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sends password accounts to wp-login
	 *
	 * @param WP_User $user
	 * @return void
	 */
	private function redirect_to_login_if_not_customer( WP_User $user ) {
		if ( ! $this->is_passwordless( $user->ID ) ) {
			$url = add_query_arg( 'action', 'purchase', wp_login_url( get_current_url() ) );
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Returns true for passwordless accounts
	 *
	 * @param integer $user_id
	 * @return boolean
	 */
	private function is_passwordless( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		return in_array( 'customer', $user->roles, true );
	}
}
