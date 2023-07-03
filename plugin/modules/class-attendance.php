<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;
use Transgression\Logger;
use Transgression\Person;

use const Transgression\PLUGIN_REST_NAMESPACE;
use const Transgression\PLUGIN_SLUG;

use function Transgression\load_view;
use function Transgression\prefix;

class Attendance extends Module {
	const ROLE_CHECKIN = 'check_in';
	const CAP_ATTENDANCE = 'view_attendance';

	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	const ICON = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI3OC4zNjkiIGhlaWdodD0iNzguMzY' .
	'5IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA3OC4zNjkgNzguMzY5IiB4bWw6c3BhY2U9InByZXNlcnZlIj' .
	'48cGF0aCBkPSJNNzguMDQ5IDE5LjAxNSAyOS40NTggNjcuNjA2YTEuMDk0IDEuMDk0IDAgMCAxLTEuNTQ4IDBMLjMyIDQwL' .
	'jAxNWExLjA5NCAxLjA5NCAwIDAgMSAwLTEuNTQ3bDYuNzA0LTYuNzA0YTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsMjAu' .
	'MTEzIDIwLjExMiA0MS4xMTMtNDEuMTEzYTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsNi43MDMgNi43MDRhMS4wOTQgMS4' .
	'wOTQgMCAwIDEgMCAxLjU0OHoiLz48L3N2Zz4=';

	protected Page $admin;

	protected ?int $product_id = null;
	/** @var \WC_Order[] Array of orders for a given event */
	protected array $orders = [];

	public function __construct() {
		if ( ! self::check_plugins() ) {
			return;
		}
		$admin = new Page( 'attendance', 'Attendance Sheet', 'Attendance', self::CAP_ATTENDANCE );
		$admin->add_render_callback( [ $this, 'render' ] );
		$admin->as_page( 'data:image/svg+xml;base64,' . self::ICON, 56 );
		$admin->add_style( 'attendance' );
		$admin->add_screen_callback( [ $this, 'pre_render' ] );
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
	}

	/**
	 * Registers REST endpoints
	 * @return void
	 */
	public function rest_api() {
		register_rest_route(
			PLUGIN_REST_NAMESPACE,
			'/orders/(?P<product_id>\d+)',
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'orders_endpoint' ],
				'args' => [
					'product_id' => [
						'required' => true,
						'validate_callback' => 'rest_is_integer',
					],
				],
				'permission_callback' => [ $this, 'can_use_endpoints' ],
			]
		);
		register_rest_route(
			PLUGIN_REST_NAMESPACE,
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
				'permission_callback' => [ $this, 'can_use_endpoints' ],
			]
		);
	}

	/**
	 * Runs admin actions when the screen is loaded
	 *
	 * @return void
	 */
	public function pre_render() {
		$product_id = absint( filter_input( INPUT_GET, 'product_id', FILTER_VALIDATE_INT ) );
		if ( ! $product_id ) {
			/** @var int[] */
			$product_ids = wc_get_products( [
				'status' => 'publish',
				'limit' => 1,
				'return' => 'ids',
			] );
			$product_id = reset( $product_ids );
		}
		$lock = false;
		if ( $product_id ) {
			$this->product_id = $product_id;
			$this->orders = $this->get_orders( $product_id );
			$lock = wp_check_post_lock( $product_id );
			if ( ! $lock ) {
				wp_set_post_lock( $product_id );
			}
		}

		$this->admin->add_script( 'attendance', [], [
			'root' => esc_url_raw( rest_url( PLUGIN_REST_NAMESPACE ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'orders' => $this->orders,
			'lock' => $lock,
		] );
	}

	/**
	 * Renders the attendance table
	 *
	 * @return void
	 */
	public function render() {
		/** @var \WC_Product[] */
		$products = wc_get_products( [
			'limit' => 100,
		] );

		$search = '';
		if ( ! empty( $_GET['search'] ) ) {
			check_admin_referer( prefix( 'attendance' ) );
			$search = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}

		$product_id = $this->product_id;
		load_view( 'attendance/header', compact( 'products', 'product_id', 'search' ) );

		load_view( 'attendance/table', [ 'orders' => $this->orders ] );
	}

	/**
	 * Returns true when a user can use the endpoints
	 *
	 * @return boolean
	 */
	public function can_use_endpoints(): bool {
		return current_user_can( self::CAP_ATTENDANCE );
	}

	/**
	 * Gets all orders for a given product
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function orders_endpoint( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$product_id = absint( $request->get_param( 'product_id' ) );
		return rest_ensure_response( $this->get_orders( $product_id ) );
	}

	/**
	 * Checks an order in or out
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
			$order->update_meta_data( 'checked_in', time() );
			$order->save();
			$this->order_to_person( $order )?->vaccinated( true );
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
		if ( ! $product_id ) {
			return [];
		}
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
		$sorted = wp_list_sort( $orders, 'name' );
		return $sorted;
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
