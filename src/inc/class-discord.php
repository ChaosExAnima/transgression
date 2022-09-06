<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Helpers\{Admin, Admin_Option};
use WP_Post;

class Discord extends Helpers\Singleton {
	protected Admin $admin;

	protected function __construct() {
		$admin = new Admin( 'discord' );
		$admin->as_post_subpage(
			Applications::POST_TYPE,
			'discord',
			'Discord'
		);
		$admin->add_action( 'test-hook', [ $this, 'send_test' ] );

		( new Admin_Option( 'app_discord_hook', 'Application Webhook' ) )
			->of_type( 'url' )
			->render_after( [ $this, 'render_test_button' ] )
			->on_page( $admin );
		( new Admin_Option( 'woo_discord_hook', 'Capitalism Webhook' ) )
			->of_type( 'url' )
			->render_after( [ $this, 'render_test_button' ] )
			->on_page( $admin );

		$this->admin = $admin;
	}

	public function init() {
		add_action( 'save_post_' . Applications::POST_TYPE, [ $this, 'send_app_message' ], 10, 3 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'send_woo_message' ] );
	}

	/**
	 * Sends message for applications.
	 *
	 * @param int $post_id The post ID
	 * @param WP_Post $post The post object
	 * @param bool $update True if this is an update
	 * @return void
	 */
	public function send_app_message( int $post_id, WP_Post $post, bool $update ): void {
		if ( $update ) {
			return; // Only for first insert
		}

		$hook = $this->admin->get_setting( 'app_discord_hook' )?->get();
		if ( ! $hook ) {
			return;
		}

		$description = sprintf(
			'There are %d open applications',
			absint( Applications::get_unreviewed_count() )
		);

		$this->send_discord_message(
			$hook,
			"New application from {$post->post_title}",
			get_edit_post_link( $post_id, 'url' ),
			$description
		);
	}

	/**
	 * Sends a message with new orders
	 *
	 * @param int $order_id The order ID
	 * @return void
	 */
	public function send_woo_message( int $order_id ): void {
		$hook = $this->admin->get_setting( 'woo_discord_hook' )?->get();
		if ( ! $hook ) {
			return;
		}

		$order = wc_get_order( $order_id );

		/** @var \WC_Order_Item_Product[] */
		$items = $order->get_items();
		$item = array_pop( $items );
		$product = $item->get_product();

		// Build up additional fields.
		$fields = [];
		if ( $product->is_type( 'variation' ) ) {
			$fields[] = [
				'name' => 'Tier',
				'value' => $product->get_attribute( 'tier' ),
			];
		}

		if ( count( $order->get_coupon_codes() ) > 0 ) {
			$fields[] = [
				'name' => 'Coupons',
				'value' => implode( ', ', $order->get_coupon_codes() ),
			];
		}

		$fields[] = [
			'name' => 'Total',
			'value' => '$' . wc_trim_zeros( $order->get_total() ),
		];
		$fields[] = [
			'name' => 'Remaining tickets',
			'value' => $product->get_stock_quantity(),
		];
		$sales = WooCommerce::get_gross_sales( $item->get_product_id() );
		$fields[] = [
			'name' => 'Total sales',
			'value' => '$' . wc_format_decimal( $sales, '', true ),
		];

		$customer_id = $order->get_customer_id();
		$customer = get_userdata( $customer_id );
		$this->send_discord_message(
			$hook,
			"New {$product->get_title()} ticket for {$customer->display_name}",
			admin_url( "post.php?post={$order_id}&action=edit" ),
			null,
			compact( 'fields' )
		);
	}

	/**
	 * Renders a button that triggers
	 *
	 * @param Admin_Option $option
	 * @return void
	 */
	public function render_test_button( Admin_Option $option ): void {
		$test_url = $this->admin->get_url( [
			'test-hook' => $option->key,
			'_wpnonce' => wp_create_nonce( "test-hook-{$option->key}" ),
		] );
		printf(
			'&nbsp;<a class="button button-secondary" href="%s" id="%s-test">Send test</a>',
			esc_url( $test_url ),
			esc_attr( $option->key )
		);
	}

	/**
	 * Sends a test message
	 *
	 * @param string $hook_type
	 * @return void
	 */
	public function send_test( string $hook_type ): void {
		check_admin_referer( "test-hook-{$hook_type}" );
		$hook = $this->admin->get_setting( $hook_type )?->get();
		if ( ! $hook ) {
			$this->admin->add_message( 'Hook not configured', 'error' );
			return;
		}

		$this->send_discord_message(
			$hook,
			'Test message',
			site_url(),
			'This is a message to check things are working!'
		);
		$this->admin->add_message( 'Sent test message', 'success' );
	}

	/**
	 * Sends a message to Discord
	 *
	 * @param string $webhook
	 * @param string $title
	 * @param string|null $url
	 * @param string|null $body The description
	 * @param array $extra_fields
	 * @return void
	 */
	protected function send_discord_message(
		string $webhook,
		string $title,
		?string $url = null,
		?string $body = null,
		array $extra_fields = []
	): void {
		$embed = array_merge( $extra_fields, [
			'title' => esc_html( $title ),
			'type' => 'rich',
			'url' => $url,
			'description' => $body,
		] );

		$args = [
			'body' => wp_json_encode( [ 'embeds' => [ $embed ] ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'blocking' => false,
		];

		wp_remote_post( $webhook, $args );
	}

}
