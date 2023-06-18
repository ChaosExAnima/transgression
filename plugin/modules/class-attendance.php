<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use Transgression\Logger;

use function Transgression\{load_view};

class Attendance extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	const ICON = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI3OC4zNjkiIGhlaWdodD0iNzguMzY' .
	'5IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA3OC4zNjkgNzguMzY5IiB4bWw6c3BhY2U9InByZXNlcnZlIj' .
	'48cGF0aCBkPSJNNzguMDQ5IDE5LjAxNSAyOS40NTggNjcuNjA2YTEuMDk0IDEuMDk0IDAgMCAxLTEuNTQ4IDBMLjMyIDQwL' .
	'jAxNWExLjA5NCAxLjA5NCAwIDAgMSAwLTEuNTQ3bDYuNzA0LTYuNzA0YTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsMjAu' .
	'MTEzIDIwLjExMiA0MS4xMTMtNDEuMTEzYTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsNi43MDMgNi43MDRhMS4wOTQgMS4' .
	'wOTQgMCAwIDEgMCAxLjU0OHoiLz48L3N2Zz4=';

	public function __construct() {
		if ( ! self::check_plugins() ) {
			return;
		}
		$admin = new Page( 'attendance', 'Attendance Sheet', 'Attendance', 'edit_products' );
		$admin->add_render_callback( [ $this, 'render' ] );
		$admin->as_page( 'data:image/svg+xml;base64,' . self::ICON, 56 );
		$admin->add_style( 'attendance' );
	}

	/**
	 * Renders the attendance table
	 *
	 * @return void
	 */
	public function render() {
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
				"SELECT order_id FROM {$wpdb->prefix}wc_order_product_lookup
				WHERE product_id = %d AND product_qty > 0
				LIMIT 200",
				$product_id
			);
			/** @var string[] */
			$order_ids = array_unique( $wpdb->get_col( $query ), SORT_NUMERIC ); // phpcs:ignore WordPress.DB
		}

		$orders = [];
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order->get_status() === 'completed' ) {
				$user = $order->get_user();
				if ( ! $user ) {
					Logger::info( "Attendance: no user for order ID {$order->get_id()}" );
					continue;
				}
				$avatar_url = '';
				if ( $user->image_url ) {
					$avatar_url = $user->image_url;
				} elseif ( $user->application ) {
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
					'vaccine' => wc_get_customer_order_count( $user->ID ) > 1,
				];
			}
		}
		$orders = wp_list_sort( $orders, 'name' );

		load_view( 'attendance/table', compact( 'products', 'product_id', 'orders' ) );
	}
}
