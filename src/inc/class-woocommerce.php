<?php declare( strict_types=1 );

namespace Transgression;

use Error;
use WC_Product;
use WP_User;

class WooCommerce extends Singleton {
	protected function __construct() {
		if ( !defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		add_action( 'template_redirect', [ $this, 'handle_login' ] );
		add_filter( 'login_message', [ $this, 'filter_login_message' ] );
		add_filter( 'the_title', [ $this, 'filter_title' ], 10, 2 );

		// Tweaks actions and filters.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		add_filter( 'wc_product_sku_enabled', '__return_false' );
		add_filter( 'woocommerce_product_tabs', '__return_empty_array' );
	}

	public function init() {
		remove_theme_support(  'wc-product-gallery-slider' );
		remove_theme_support( 'wc-product-gallery-zoom' );
		remove_theme_support( 'wc-product-gallery-lightbox' );
	}

	public function skip_cart() {
		if ( !is_cart() ) {
			return;
		}
		$target = WC()->cart->is_empty()
			? wc_get_page_permalink( 'shop' )
			: wc_get_checkout_url();
		wp_safe_redirect( $target );
		exit;
	}

	public function handle_login() {
		// Skip for logged in users.
		if ( is_user_logged_in() ) {
			return;
		}

		// Try logging in?
		if ( $this->check_login( get_current_url() ) ) {
			$user_id = email_exists( $_GET['email'] );
			wp_set_auth_cookie( $user_id );
			$redirect_url = add_query_arg( 'login', 'done', strtok( get_current_url(), '?' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( empty( $_POST['login-email'] ) || is_admin() ) {
			return;
		}

		$url = add_query_arg( 'login', 'sent', get_current_url() );

		$email = sanitize_email( $_POST['login-email'] );
		$user_id = email_exists( $email );
		if ( is_integer( $user_id ) ) {
			$this->send_login_email( $user_id );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function filter_login_message( string $message ): string {
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'purchase' ) {
			$message .= '<p class="message">You need to log in to buy tickets</p>';
		}
		return $message;
	}

	public function filter_title( string $title, int $post_id ): string {
		if ( get_post_type( $post_id ) === 'product' ) {
			return ltrim( str_replace( 'Transgression:', '', $title ) );
		}
		return $title;
	}

	public static function add_title_prefix( WC_Product $product ): bool {
		return strpos( $product->get_name(), 'Transgression:' ) === 0;
	}

	protected function send_login_email( int $user_id ) {
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

	protected function check_login( string $url ): bool {
		// First, is this even valid?
		if ( empty( $_GET['login'] ) || $_GET['login'] !=='purchase' || empty( $_GET['email'] ) ) {
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
		return hash_equals( $token, $_GET['token'] );
	}

	protected function redirect_to_login_if_not_customer( WP_User $user ) {
		if ( !wc_user_has_role( $user, 'customer' ) ) {
			$url = add_query_arg( 'action', 'purchase', wp_login_url( get_current_url() ) );
			wp_safe_redirect( $url );
			exit;
		}
	}
}
