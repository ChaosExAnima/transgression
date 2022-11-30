<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Logger;

class WooCommerce extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = ['woocommerce'];

	public function __construct( protected Logger $logger ) {
		if ( !self::check_plugins() ) {
			return;
		}
		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		add_action( 'template_redirect', [ $this, 'clear_cart' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_variation_dupes' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'skip_processing' ] );

		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		add_filter( 'woocommerce_navigation_wp_toolbar_disabled', '__return_false' );
		add_filter( 'woocommerce_customer_meta_fields', '__return_empty_array' );
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
