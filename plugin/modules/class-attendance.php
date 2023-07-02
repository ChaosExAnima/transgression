<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use Transgression\Logger;
use Transgression\Person;

use function Transgression\{load_view};

class Attendance extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	const ROLE_CHECKIN = 'check_in';

	const CAP_ATTENDANCE = 'view_attendance';

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
		$admin = new Page( 'attendance', 'Attendance Sheet', 'Attendance', self::CAP_ATTENDANCE );
		$admin->add_render_callback( [ $this, 'render' ] );
		$admin->as_page( 'data:image/svg+xml;base64,' . self::ICON, 56 );
		$admin->add_style( 'attendance' );

		parent::__construct();
	}

	public function init() {
		add_role(
			self::ROLE_CHECKIN,
			'Check-in',
			[
				'read' => true,
				'view_admin_dashboard' => true, // This lets people see the back end
				self::CAP_ATTENDANCE => true,
			],
		);
		$roles = [ 'administrator', 'shop_manager' ];
		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			$role->add_cap( self::CAP_ATTENDANCE );
		}
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

		$orders = $this->get_orders( $product_id );

		load_view( 'attendance/table', compact( 'products', 'product_id', 'orders' ) );
	}

	/**
	 * Get order data for a product
	 *
	 * @param int $product_id
	 * @return array
	 */
	protected function get_orders( int $product_id ): array {
		/** @var string[] */
		$order_ids = [];
		if ( $product_id ) {
			global $wpdb;
			$query = $wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}wc_order_product_lookup
				WHERE product_id = %d AND product_qty > 0
				LIMIT 1000",
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
				$person = new Person( $user );

				$orders[] = [
					'id' => $order->get_id(),
					'pic' => $person->image_url(),
					'name' => $person->name(),
					'email' => $person->email(),
					'user_id' => $user->ID,
					'vaccine' => $person->vaccinated(),
					'volunteer' => $this->is_volunteer( $order ),
					'covid_test' => $order->get_meta( 'covid_test' ),
					'checked_in' => $order->get_meta( 'checked_in' ),
				];
			}
		}
		return wp_list_sort( $orders, 'name' );
	}

	/**
	 * Returns true if someone is probably a volunteer
	 *
	 * @param \WC_Order $order
	 * @return boolean
	 */
	protected function is_volunteer( \WC_Order $order ): bool {
		if ( count( $order->get_coupons() ) > 0 ) {
			return true;
		}
		foreach ( $order->get_items() as $item ) {
			if ( false !== stripos( $item->get_name(), 'volunteer' ) ) {
				return true;
			}
		}
		return false;
	}
}
