<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use Transgression\Logger;
use Transgression\Person;

use function Transgression\load_view;
use function Transgression\prefix;

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

	protected Page $admin;

	public function __construct() {
		if ( ! self::check_plugins() ) {
			return;
		}
		$admin = new Page( 'attendance', 'Attendance Sheet', 'Attendance', self::CAP_ATTENDANCE );
		$admin->add_render_callback( [ $this, 'render' ] );
		$admin->as_page( 'data:image/svg+xml;base64,' . self::ICON, 56 );
		$admin->add_style( 'attendance' );
		$this->admin = $admin;

		add_action( 'rest_api_init', [ $this, 'rest_api' ] );
		parent::__construct();
	}

	/**
	 * Init actions
	 * @return void
	 */
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

		$this->admin->add_script( 'attendance', [], [
			'root' => esc_url_raw( rest_url( prefix( 'v1', '/' ) ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	/**
	 * Registers REST endpoints
	 * @return void
	 */
	public function rest_api() {
		register_rest_route(
			prefix( 'v1', '/' ),
			'/checkin/(?P<id>\d+)',
			[
				'methods' => [ 'GET', 'PUT' ],
				'callback' => [ $this, 'checkin_endpoint' ],
				'args' => [
					'id' => [
						'required' => true,
						'validate_callback' => 'rest_is_integer',
					],
				],
				'permission_callback' => function (): bool {
					return current_user_can( self::CAP_ATTENDANCE );
				},
			]
		);
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
	 * REST API response
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function checkin_endpoint( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$order_id = $request->get_param( 'id' );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'no-id', 'Missing order ID', [ 'status' => 404 ] );
		}

		$update = $request->get_method() !== \WP_REST_Server::READABLE;
		if ( $update ) {
			$current = (bool) $order->get_meta( 'checked_in' );
			$order->update_meta_data( 'checked_in', intval( ! $current ) );
			$order->save();

			$person = $this->order_to_person( $order );
			if ( ! $current && $person && ! $person->vaccinated() ) {
				$person->vaccinated( true );
			}
		}

		return rest_ensure_response( $this->get_order_data( $order ) );
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
				$order_data = $this->get_order_data( $order );
				if ( ! $order_data ) {
					Logger::info( "Attendance: no data for order ID {$order->get_id()}" );
					continue;
				}
				$orders[] = $order_data;
			}
		}
		return wp_list_sort( $orders, 'name' );
	}

	/**
	 * Gets a person from an order
	 *
	 * @param \WC_Order $order
	 * @return Person|null
	 */
	protected function order_to_person( \WC_Order $order ): ?Person {
		$user = $order->get_user();
		if ( ! $user ) {
			return null;
		}
		return new Person( $user );
	}

	/**
	 * Gets formatted order data
	 *
	 * @param \WC_Order $order
	 * @return array|null
	 */
	protected function get_order_data( \WC_Order $order ): ?array {
		$person = $this->order_to_person( $order );
		if ( ! $person ) {
			return null;
		}
		return [
			'id' => $order->get_id(),
			'pic' => $person->image_url(),
			'name' => $person->name(),
			'email' => $person->email(),
			'user_id' => $person->user_id(),
			'volunteer' => $this->is_volunteer( $order ),
			'checked_in' => (bool) $order->get_meta( 'checked_in' ),
			'vaccinated' => $person->vaccinated(),
		];
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
