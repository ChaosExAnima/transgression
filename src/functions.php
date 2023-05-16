<?php declare( strict_types=1 );

namespace TransgressionTheme;

use WC_Order;
use WP_Term;

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

/**
 * Removes annoying Woo stylesheets
 * @param array $styles Array of stylesheets
 * @return array
 */
function wc_filter_styles( array $styles ): array {
	unset( $styles['woocommerce-layout'] );
	unset( $styles['woocommerce-smallscreen'] );
	return $styles;
}
add_filter( 'woocommerce_enqueue_styles', cb( 'wc_filter_styles' ) );

/**
 * Displays text to indicate past events and whether events are available
 *
 * @return void
 */
function wc_add_product_category() {
	static $shown_header = false;
	if ( $shown_header || ! has_term( '', 'product_cat' ) ) {
		return;
	}
	$product_id = get_the_ID();
	$categories = get_the_terms( $product_id, 'product_cat' );
	if ( false === $categories || is_wp_error( $categories ) ) {
		return;
	}
	$default_category_id = absint( get_option( 'default_product_cat', 0 ) );
	$not_uncategorized = array_filter( $categories, function ( \WP_Term $term ) use ( $default_category_id ): bool {
		return $term->term_id !== $default_category_id;
	} );
	if ( 0 === count( $not_uncategorized ) ) {
		return;
	}

	/** @var \WP_Query $wp_query */
	global $wp_query;
	if ( 0 === $wp_query->current_post ) {
		printf(
			'<h2 class="no-current-event">%s</h2>',
			esc_html__( 'There are no available events right now', 'transgression' )
		);
	}

	printf(
		'<h3 class="past-events">%s</h3>',
		esc_html__( 'Past events', 'transgression' )
	);
	$shown_header = true;
}
add_action( 'woocommerce_shop_loop', cb( 'wc_add_product_category' ), 20 );

// Disable Jetpack Blaze
add_filter( 'jetpack_blaze_enabled', '__return_false' );
