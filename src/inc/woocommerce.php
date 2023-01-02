<?php declare( strict_types=1 );

namespace TransgressionTheme;

use WC_Product;

if ( !defined( 'WC_PLUGIN_FILE' ) ) {
	return;
}

// Tweaks actions and filters.
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );

function filter_wc_title( string $title, int $post_id ): string {
	if ( get_post_type( $post_id ) === 'product' ) {
		return ltrim( str_replace( 'Transgression:', '', $title ) );
	}
	return $title;
}
add_filter( 'the_title', cb( 'filter_wc_title' ), 10, 2 );

function render_wc_clear_cart() {
	printf(
		'<p><a href="%s" class="clear-cart">Clear Cart</a></p>',
		esc_url( add_query_arg( 'empty_cart', 'yes' ) )
	);
}
add_action( 'woocommerce_checkout_order_review', cb( 'render_wc_clear_cart' ), 15 );

function add_wc_title_prefix( WC_Product $product ): bool {
	return strpos( $product->get_name(), 'Transgression:' ) === 0;
}
