<?php declare( strict_types=1 );

namespace Transgression;

class WooCommerce extends Singleton {
	protected function __construct() {
		if ( !defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}

		// Tweaks actions and filters.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		add_filter( 'wc_product_sku_enabled', '__return_false' );
		add_filter( 'woocommerce_product_tabs', '__return_empty_array' );
	}

	public function init() {
		remove_theme_support( 'wc-product-gallery-slider' );
		remove_theme_support( 'wc-product-gallery-zoom' );
		remove_theme_support( 'wc-product-gallery-lightbox' );
	}
}
