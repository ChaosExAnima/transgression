<?php declare( strict_types=1 );

namespace TransgressionTheme;

use WC_Order;

function cb( string $func ): callable {
	return __NAMESPACE__ . '\\' . $func;
}

function init() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );

	add_theme_support( 'woocommerce' );
	remove_theme_support( 'wc-product-gallery-slider' );
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );

	// Woo
	if ( defined( 'WC_PLUGIN_FILE' ) ) {
		add_action( 'woocommerce_checkout_order_review', cb( 'render_wc_clear_cart' ), 15 );

		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
		add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
	}
}
add_action( 'init', cb( 'init' ) );

function styles() {
	$theme = wp_get_theme();
	$version = $theme->exists() ? $theme->get( 'Version' ) : 'unknown';
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ), [], $version, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function redirect() {
	if ( is_author() ) {
		wp_safe_redirect( home_url(), 301 );
		exit;
	}
}
add_action( 'template_redirect', cb( 'redirect' ) );

function render_wc_clear_cart() {
	printf(
		'<p><a href="%s" class="clear-cart">Clear Cart</a></p>',
		esc_url( add_query_arg( 'empty_cart', 'yes' ) )
	);
}

function order_greeting( WC_Order $order ) {
	$user_id = $order->get_customer_id();
	$user = get_user_by( 'id', $user_id );
	if ( ! $user_id || ! $user ) {
		esc_html_e( 'Hi there,', 'transgression' );
	}
	printf(
		/* translators: %s: Customer first name */
		esc_html__( 'Hi %s,', 'transgression' ),
		esc_html( $user->first_name )
	);
}

add_filter( 'jetpack_blaze_enabled', '__return_false' );
