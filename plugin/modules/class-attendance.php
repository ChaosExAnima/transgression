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

	const ICON = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI3OC4zNjkiIGhlaWdodD0iNzguMzY' .
	'5IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA3OC4zNjkgNzguMzY5IiB4bWw6c3BhY2U9InByZXNlcnZlIj' .
	'48cGF0aCBkPSJNNzguMDQ5IDE5LjAxNSAyOS40NTggNjcuNjA2YTEuMDk0IDEuMDk0IDAgMCAxLTEuNTQ4IDBMLjMyIDQwL' .
	'jAxNWExLjA5NCAxLjA5NCAwIDAgMSAwLTEuNTQ3bDYuNzA0LTYuNzA0YTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsMjAu' .
	'MTEzIDIwLjExMiA0MS4xMTMtNDEuMTEzYTEuMDk1IDEuMDk1IDAgMCAxIDEuNTQ4IDBsNi43MDMgNi43MDRhMS4wOTQgMS4' .
	'wOTQgMCAwIDEgMCAxLjU0OHoiLz48L3N2Zz4=';

	protected Page $admin;

	protected int $attachment_id = 0;

	public function __construct( protected ForbiddenTickets $tickets ) {
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
		$roles = [ 'administrator' ];
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
			'/checkin/(?P<user_id>\d+)/(?P<id>\w+)',
			[
				'methods' => [ 'GET', 'PUT' ],
				'callback' => [ $this, 'checkin_endpoint' ],
				'args' => [
					'user_id' => [
						'required' => true,
						'validate_callback' => 'rest_is_integer',
					],
					'id' => [
						'required' => true,
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
		$attachment_id = 0;
		if ( isset( $_GET['attachment_id'] ) ) {
			check_admin_referer( prefix( 'attendance' ) );
			$attachment_id = absint( $_GET['attachment_id'] );
		}

		$this->admin->add_script( 'attendance', [], [
			'root' => esc_url_raw( rest_url( PLUGIN_REST_NAMESPACE ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'orders' => $this->users_from_csv( $attachment_id ),
		] );
	}

	/**
	 * Renders the attendance table
	 *
	 * @return void
	 */
	public function render() {
		$attachments = new \WP_Query( [
			'post_mime_type' => 'text/csv',
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			'posts_per_page' => -1,
		] );
		$attachment_id = $attachments->have_posts() ? $attachments->posts[0]->ID : 0;
		if ( isset( $_GET['attachment_id'] ) ) {
			check_admin_referer( prefix( 'attendance' ) );
			$attachment_id = absint( $_GET['attachment_id'] );
		}

		$orders = $this->users_from_csv( $attachment_id );

		$search = '';
		if ( ! empty( $_GET['search'] ) ) {
			check_admin_referer( prefix( 'attendance' ) );
			$search = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}

		load_view( 'attendance/header', compact( 'attachment_id', 'attachments', 'search' ) );
		load_view( 'attendance/table', [ 'orders' => $orders ] );
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
		return rest_ensure_response( $this->users_from_csv( $product_id ) );
	}

	/**
	 * Checks an order in or out
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function checkin_endpoint( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$order_id = $request->get_param( 'id' );
		if ( ! $order_id ) {
			return new \WP_Error( 'no-id', 'Missing order ID', [ 'status' => 404 ] );
		}
		$user_id = $request->get_param( 'user_id' );
		if ( ! $user_id ) {
			return new \WP_Error( 'no-user-id', 'Missing user ID', [ 'status' => 404 ] );
		}
		$user = get_user_by( 'ID', $user_id );

		$update = $request->get_method() !== \WP_REST_Server::READABLE;
		if ( $update ) {
			update_option( 'checked_in_' . $order_id, true );
			( new Person( $user ) )->vaccinated( true );
		}

		return rest_ensure_response( $this->get_order_data( $user, $order_id ) );
	}

	/**
	 * Get users from a CSV file
	 *
	 * @param int $attachment_id
	 * @return null|array
	 */
	protected function users_from_csv( int $attachment_id ): array {
		if ( ! $attachment_id ) {
			return [];
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path ) {
			Logger::error( "Could not get path for attachment {$attachment_id}" );
			return [];
		}

		$handle = fopen( $path, 'r' ); // @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			Logger::error( "Could not open file {$path}" );
			return [];
		}
		$codes = [];
		$row = fgetcsv( $handle, 1000, ',' );
		while ( $row !== false ) {
			if ( isset( $row[8] ) && 'Promo Code' !== $row[8] ) {
				$codes[ $row[8] ] = $row[1];
			}
			$row = fgetcsv( $handle, 1000, ',' );
		}
		fclose( $handle ); // @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! count( $codes ) ) {
			return [];
		}

		$users = new \WP_User_Query( [
			'orderby' => 'display_name',
			'meta_query' => [ // @phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key' => ForbiddenTickets::USER_CODE_KEY,
					'value' => array_keys( $codes ),
				],
			],
		] );

		$orders = [];
		foreach ( $users->get_results() as $user ) {
			$code = $this->tickets->get_code( $user->ID ) ?? '';
			$orders[] = $this->get_order_data( $user, $codes[ $code ] );
		}

		return $orders;
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
	 * @param \WP_User $user
	 * @param string $order_id
	 * @return array|null
	 */
	protected function get_order_data( \WP_User $user, string $order_id ): ?array {
		$person = new Person( $user );
		return [
			'id' => $order_id,
			'pic' => $person->image_url(),
			'name' => $person->name(),
			'email' => $person->email(),
			'user_id' => $person->user_id(),
			'volunteer' => false,
			'checked_in' => (bool) get_option( 'checked_in_' . $order_id, false ),
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
