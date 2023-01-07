<?php declare( strict_types=1 );

namespace Transgression;

use WC_Product;

class WooCommerce extends Helpers\Singleton {
	protected function __construct() {
		if ( !defined( 'WC_PLUGIN_FILE' ) ) {
			return;
		}

		// Display
		add_filter( 'the_title', [ $this, 'filter_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_review', [ $this, 'render_clear_cart' ], 15 );
		add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_styles' ] );

		// Purchasing
		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		add_action( 'template_redirect', [ $this, 'clear_cart' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_variation_dupes' ], 10, 2 );
		add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_required_billing_shipping' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'skip_processing' ] );

		// Attendance page
		add_action( 'admin_menu', [ $this, 'attendance_menu' ] );

		// Tweaks actions and filters.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
		add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		add_filter( 'woocommerce_navigation_wp_toolbar_disabled', '__return_false' );
		add_filter( 'woocommerce_customer_meta_fields', '__return_empty_array' );
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

	public function remove_required_billing_shipping( array $fields ): array {
		unset( $fields['billing'] );
		unset( $fields['shipping'] );
		return $fields;
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

	public function load_admin_styles() {
		$screen = get_current_screen();
		if ( $screen->post_type === 'shop_order' && $screen->base === 'edit' ) {
			wp_enqueue_style(
				'transgression-wc-print',
				get_theme_file_uri( 'assets/woo-print.css' ),
				[],
				null,
				'print'
			);
		} else if ( $screen->base === 'toplevel_page_transgression_attendance' ) {
			wp_enqueue_style(
				'transgression-attendance',
				get_theme_file_uri( 'assets/attendance.css' ),
			);
		}
	}

	/**
	 * Loads attendance menu
	 *
	 * @return void
	 */
	public function attendance_menu() {
		$icon = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI3OC4zNjkiIGhlaWdodD0iNzguMzY' .
			'5IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA3OC4zNjkgNzguMzY5IiB4bWw6c3BhY2U9InByZXNlcnZlIj' .
			'48cGF0aCBkPSJNNzguMDQ5IDE5LjAxNSAyOS40NTggNjcuNjA2YTEuMDk0IDEuMDk0IDAgMCAxLTEuNTQ4IDBMLjMyIDQwL' .
			'jAxNWExLjA5NCAxLjA5NCAwIDAgMSAwLTEuNTQ3bDYuNzA0LTYuNzA0YTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsMjAu' .
			'MTEzIDIwLjExMiA0MS4xMTMtNDEuMTEzYTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsNi43MDMgNi43MDRhMS4wOTQgMS4' .
			'wOTQgMCAwIDEgMCAxLjU0OHoiLz48L3N2Zz4=';
		add_menu_page(
			'Attendance Sheet',
			'Attendance',
			'edit_products',
			'transgression_attendance',
			[ $this, 'attendance_render' ],
			"data:image/svg+xml;base64,{$icon}",
			'56'
		);
	}

	/**
	 * Renders the attendance table
	 *
	 * @return void
	 */
	public function attendance_render() {
		/** @var \WC_Product[] */
		$products = wc_get_products( [
			'limit' => 10,
		] );
		$product_id = absint( filter_input( INPUT_GET, 'product_id', FILTER_VALIDATE_INT ) );
		if ( ! $product_id && count( $products ) ) {
			$product_id = $products[0]->get_id();
		}

		/** @var string[] */
		$order_ids = [];
		if ( $product_id ) {
			global $wpdb;
			$query = $wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}wc_order_product_lookup WHERE product_id = %d LIMIT 200",
				$product_id
			);
			/** @var string[] */
			$order_ids = array_unique( $wpdb->get_col( $query ), SORT_NUMERIC );
		}

		$orders = [];
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order->get_status() === 'completed' ) {
				$user = $order->get_user();
				if ( ! $user ) {
					log_error( "No user for order ID {$order->get_id()}" );
					continue;
				}
				$avatar_url = '';
				if ( $user->image_url ) {
					$avatar_url = $user->image_url;
				} else if ( $user->application ) {
					$app = get_post( $user->application );
					$avatar_url = $app->photo_img ?: $app->photo_url;
				}
				if ( $avatar_url && function_exists( 'jetpack_photon_url' ) ) {
					$avatar_url = jetpack_photon_url( $avatar_url, [ 'resize' => '200,200' ] );
				}
				$is_volunteer = false;
				if ( count( $order->get_coupons() ) > 0 ) {
					$is_volunteer = true;
				}
				foreach ( $order->get_items() as $item ) {
					if ( false !== stripos( $item->get_name(), 'volunteer' ) ) {
						$is_volunteer = true;
						break;
					}
				}
				$orders[] = [
					'id' => $order->get_id(),
					'pic' => $avatar_url,
					'name' => ucwords( $user->display_name ),
					'email' => $user->user_email,
					'user_id' => $user->ID,
					'volunteer' => $is_volunteer,
				];
			}
		}
		$orders = wp_list_sort( $orders, 'name' );

		load_view( 'attendance-table', compact( 'products', 'product_id', 'orders' ) );
	}

	public static function add_title_prefix( WC_Product $product ): bool {
		return strpos( $product->get_name(), 'Transgression:' ) === 0;
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

	public static function get_total_sales_by_status( int $product_id, string $order_status = 'completed' ): int {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT SUM( product_qty )
			FROM {$wpdb->prefix}wc_order_product_lookup
			INNER JOIN {$wpdb->posts}
			ON order_id = {$wpdb->posts}.ID
			AND post_status = %s
			AND product_id = %d",
			$order_status,
			$product_id
		);
		$value = $wpdb->get_var( $query );
		return intval( $value );
	}
}
