<?php declare( strict_types=1 );

namespace Transgression;

use WC_Product;
use WP_User;

class WooCommerce extends Singleton {
	protected function __construct() {
		if ( !defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}

		// Display
		add_filter( 'the_title', [ $this, 'filter_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_review', [ $this, 'render_clear_cart' ], 15 );

		// Purchasing
		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		add_action( 'template_redirect', [ $this, 'clear_cart' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_variation_dupes' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'skip_processing' ] );

		// Tweaks actions and filters.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
		add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
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

	public function clear_cart() {
		if ( !is_checkout() || empty( $_GET['empty_cart'] ) || $_GET['empty_cart'] !== 'yes' ) {
			return;
		}

		WC()->cart->empty_cart();
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	public function filter_title( string $title, int $post_id ): string {
		if ( get_post_type( $post_id ) === 'product' ) {
			return ltrim( str_replace( 'Transgression:', '', $title ) );
		}
		return $title;
	}

	public function render_clear_cart() {
		printf(
			'<p><a href="%s" class="clear-cart">Clear Cart</a></p>',
			esc_url( add_query_arg( 'empty_cart', 'yes' ) )
		);
	}

	public function skip_processing( int $order_id ) {
		if ( !$order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		$order->update_status( 'completed' );
	}

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

	public static function add_title_prefix( WC_Product $product ): bool {
		return strpos( $product->get_name(), 'Transgression:' ) === 0;
	}
}
