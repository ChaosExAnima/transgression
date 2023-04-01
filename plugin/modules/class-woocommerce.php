<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Option_Checkbox;
use Transgression\Admin\Page;

use function Transgression\load_view;

class WooCommerce extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = ['woocommerce/woocommerce.php'];

	public function __construct( protected Page $settings_page ) {
		if ( ! self::check_plugins() ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		add_action( 'template_redirect', [ $this, 'clear_cart' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_variation_dupes' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'skip_processing' ] );

		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		add_filter( 'woocommerce_navigation_wp_toolbar_disabled', '__return_false' );

		$this->register_settings(); // Adds settings

		remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
		add_action( 'woocommerce_single_variation', [ $this, 'add_login_button' ], 20 );
	}

	protected function register_settings() {
		/** @var \Transgression\Admin\Option[] */
		$settings = [];

		$settings[] = $shop_title = ( new Option_Checkbox( 'shop_title', 'Show shop page title', 1 ) )
			->describe( 'Changes the page title to use your page title instead of Products' );
		if ( $shop_title->get() ) {
			add_filter( 'post_type_archive_title', [ $this, 'show_shop_page_title' ], 10, 2 );
		}

		$settings[] = $breadcrumbs = new Option_Checkbox( 'remove_breadcrumbs', 'Remove breadcrumbs', 0 );
		if ( $breadcrumbs->get() ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}

		$settings[] = $sku = new Option_Checkbox( 'remove_sku', 'Disable SKU stuff', 1 );
		if ( $sku->get() ) {
			add_filter( 'wc_product_sku_enabled', '__return_false' );
		}

		$settings[] = $hide_category = ( new Option_Checkbox( 'hide_category', 'Hide product categories and tags', 1 ) )
			->describe( 'Removes product categories and tags from being shown to people on the site' );
		if ( $hide_category->get() ) {
			add_filter( 'get_the_terms', [ $this, 'hide_product_tags' ], 10, 3 );
		}

		// Adds all these settings.
		$this->settings_page->add_section( 'woo', 'WooCommerce' );
		foreach ( $settings as $setting ) {
			$this->settings_page->add_setting( $setting->in_section( 'woo' ) );
		}
	}

	/**
	 * Skips the cart page
	 */
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

	/**
	 * Clears the when a query var `empty_cart=yes` is passed
	 */
	public function clear_cart() {
		if ( !is_checkout() || empty( $_GET['empty_cart'] ) || $_GET['empty_cart'] !== 'yes' ) {
			return;
		}

		WC()->cart->empty_cart();
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	/**
	 * Skips processing status as it doesn't matter
	 *
	 * @param int $order_id The order ID
	 * @return void
	 */
	public function skip_processing( int $order_id ) {
		if ( !$order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		$order->update_status( 'completed' );
	}

	/**
	 * Prevents adding variations of the same event to the cart
	 *
	 * @param bool $is_valid Filter value
	 * @param int $product_id The product ID
	 * @return bool
	 */
	public function prevent_variation_dupes( bool $is_valid, int $product_id ): bool {
		if ( !$is_valid ) {
			return $is_valid;
		}

		// Ensure that the user is logged in.
		if ( ! is_user_logged_in() ) {
			wc_add_notice( 'You must be logged in first.', 'error' );
			return false;
		}

		// Ensure the product is not already in the cart.
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents as $cart_product ) {
			if ( $cart_product['product_id'] === $product_id ) {
				wc_add_notice( 'You already have this event in your cart.', 'error' );
				return false;
			}
		}

		// If the user is logged in, verify whether they've previously bought a ticket.
		if ( wc_customer_bought_product( '', get_current_user_id(), $product_id ) ) {
			wc_add_notice( 'You have already purchased a ticket to this event.', 'error' );
			return false;
		}

		return $is_valid;
	}

	/**
	 * Settings
	 */
	public function show_shop_page_title( string $page_title, string $post_type ): string {
		if ( $post_type !== 'product' ) {
			return $page_title;
		}

		$page_id = wc_get_page_id( 'shop' );
		return get_the_title( $page_id );
	}

	public function hide_product_tags( mixed $terms, int $post_id, string $taxonomy ): array {
		if ( is_admin() || ( $taxonomy !== 'product_cat' && $taxonomy !== 'product_cat' ) ) {
			return $terms;
		}
		return [];
	}

	public function add_login_button(): void {
		if ( is_user_logged_in() ) {
			woocommerce_single_variation_add_to_cart_button();
		} else {
			load_view( 'login-form' );
		}
	}

	/**
	 * Gets gross sales for a given product
	 *
	 * @param int $product_id
	 * @return float
	 */
	public static function get_gross_sales( int $product_id ): float {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT SUM(product_gross_revenue)
			FROM {$wpdb->prefix}wc_order_product_lookup
			WHERE product_id = %d",
			$product_id
		);
		$value = $wpdb->get_var( $query );
		return floatval( $value );
	}
}
