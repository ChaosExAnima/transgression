<?php declare( strict_types=1 );

namespace Transgression;

use WC_Product;

class WooCommerce extends Singleton {
	protected function __construct() {
		if ( !defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}

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

	public function filter_title( string $title, int $post_id ): string {
		if ( get_post_type( $post_id ) === 'product' ) {
			return ltrim( str_replace( 'Transgression:', '', $title ) );
		}
		return $title;
	}

	public static function add_title_prefix( WC_Product $product ): bool {
		return strpos( $product->get_name(), 'Transgression:' ) === 0;
	}
}
