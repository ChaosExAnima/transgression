<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\{Logger, Person};
use Transgression\Modules\Email\Emailer;
use WP_User;

use function Transgression\{get_current_url, load_view, prefix, strip_query};

class People extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	public function __construct( protected Emailer $emailer ) {
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
		add_action( 'wp_dashboard_setup', [ $this, 'register_widgets' ] );
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
		// phpcs:disable WordPress.Security.NonceVerification
		// Skip for logged in users.
		if ( is_user_logged_in() ) {
			return;
		}

		$current_url = strip_query( get_current_url() );

		// Try logging in?
		if ( $this->check_login( get_current_url() ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$user_id = email_exists( sanitize_email( wp_unslash( $_GET['email'] ) ) );
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

		// Set the session cookie so notices work.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$email = sanitize_email( wp_unslash( $_POST['login-email'] ) );
		$user_id = email_exists( $email );
		$message = 'Uh-oh, something happened. Try again or %2$s.';
		$type = 'notice';
		if ( $user_id ) {
			$key = $this->get_login_key( $email );
			if ( get_transient( $key ) !== false ) {
				Logger::info( "Repeat login attempt for {$email}" );
				$message = 'This email was already used to log in. If you haven&rsquo;t gotten it yet, ' .
					'give it five minutes and try again.';
			} else {
				$this->send_login_email( $user_id );
				$message = 'Check your email %1$s for a login link. If you don&rsquo;t see it, %2$s.';
				$type = 'success';
			}
		} else {
			Logger::info( "Unknown login from {$email}" );
			$message = 'We don&rsquo;t recognize the email %1$s. If you have issues with your email, %2$s.';
			$type = 'error';
		}

		wc_add_notice( sprintf(
			$message,
			esc_html( $email ),
			sprintf(
				'<a href="%s" target="_blank">contact us</a>',
				esc_url( 'mailto:' . get_option( 'admin_email' ) . '?subject=Login Issues' )
			)
		), $type );
		wp_safe_redirect( $current_url );
		exit;
		// phpcs:enable
	}

	public function redirect_to_profile() {
		if ( ! is_edit_account_page() || ! is_user_logged_in() ) {
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
	 * Registers search widget
	 *
	 * @return void
	 */
	public function register_widgets() {
		if ( ! current_user_can( 'list_users' ) ) {
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
		if ( ! current_user_can( 'list_users' ) ) {
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
		if ( ! current_user_can( 'list_users' ) ) {
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
	 * Filters login message for Woo
	 *
	 * @param string $message
	 * @return string
	 */
	public function filter_login_message( string $message ): string {
		// phpcs:ignore WordPress.Security.NonceVerification
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
		// phpcs:ignore WordPress.Security.NonceVerification
		$new_pronouns = sanitize_text_field( wp_unslash( $_POST['account_pronouns'] ?? '' ) );
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

	private function get_login_key( string $email ): string {
		return "user_login_{$email}";
	}

	/**
	 * Sends a login email
	 *
	 * @param integer $user_id
	 * @return void
	 */
	private function send_login_email( int $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			Logger::error( "Tried to log in with user ID of {$user_id}" );
			return;
		}

		$this->redirect_to_login_if_not_customer( $user );

		// Generate a random value.
		$key = $this->get_login_key( $user->user_email );
		$token = wp_generate_password( 20, false );
		set_transient( $key, $token, HOUR_IN_SECONDS );

		// Build a URL.
		$args = [
			'login' => 'purchase',
			'email' => rawurlencode( $user->user_email ),
			'token' => $token,
		];
		$url = add_query_arg( $args, get_current_url() );
		$hash = wp_hash( $url );
		$login_url = add_query_arg( 'hash', $hash, $url );

		// Send the email.
		$email = $this->emailer->create();
		$email
			->to_user( $user_id )
			->with_template( 'people_login' )
			->with_subject( 'Login here' )
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
		// phpcs:disable WordPress.Security.NonceVerification
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
		$email = sanitize_email( wp_unslash( $_GET['email'] ) );
		$hash = wp_hash( remove_query_arg( 'hash', $url ) );
		if ( empty( $_GET['hash'] ) || ! hash_equals( $hash, wp_unslash( $_GET['hash'] ) ) ) {
			Logger::info( "Login hash check failed for {$email}" );
			return false;
		}

		// Does this user exist?
		$user_id = email_exists( $email );
		if ( ! $user_id ) {
			Logger::info( "Login email check failed for {$email}" );
			return false;
		}

		// Check the token.
		$key = $this->get_login_key( $email );
		$token = get_transient( $key );
		$provided_token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		if ( false === $token || ! hash_equals( $token, $provided_token ) ) {
			if ( $token ) {
				Logger::info( "Login token check mismatch for {$email}: {$token} vs {$provided_token}" );
			} else {
				Logger::info( "Login token check expired for {$email}" );
			}
			return false;
		}

		return true;
		// phpcs:enable
	}

	/**
	 * Sends password accounts to wp-login
	 *
	 * @param WP_User $user
	 * @return void
	 */
	public function redirect_to_login_if_not_customer( WP_User $user ) {
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
