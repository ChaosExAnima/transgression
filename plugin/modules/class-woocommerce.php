<?php declare( strict_types=1 );

namespace Transgression\Modules;

use DateTimeImmutable;
use Transgression\Admin\{Option_Checkbox, Page_Options};

use function Transgression\{get_safe_post, load_view, prefix};

class WooCommerce extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	public function __construct( protected Page_Options $admin ) {
		if ( ! self::check_plugins() ) {
			return;
		}

		// Redirects people to checkout if they try to access the cart
		add_action( 'template_redirect', [ $this, 'skip_cart' ] );
		// Prevents dupes on adding to the cart
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_variation_dupes' ], 10, 2 );
		// Redirects to checkout when adding an item
		add_filter( 'woocommerce_add_to_cart_redirect', 'wc_get_checkout_url' );
		// Shows an error if people try to check out with a ticket for the same event
		add_action( 'woocommerce_checkout_process', [ $this, 'prevent_checkout_dupes' ] );
		// Clears the cart and redirects to the shop
		add_action( 'template_redirect', [ $this, 'clear_cart' ] );
		// Automatically skip the processing step
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'skip_processing' ] );

		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' ); // Never require shipping
		add_filter( 'woocommerce_navigation_wp_toolbar_disabled', '__return_false' ); // Enables WP toolbar
		add_filter( 'woocommerce_checkout_update_customer_data', '__return_false' ); // Prevents checkout from updating user data

		$this->register_settings(); // Adds settings

		// Removes add to cart button and instead shows login for variation pages
		remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
		add_action( 'woocommerce_single_variation', [ $this, 'add_login_button' ], 20 );

		// Tweaks admin UI
		add_action( 'save_post_product', [ $this, 'save_event_times' ], 20 );
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'filter_admin_order_columns' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'admin_order_custom_columns' ] );
	}

	protected function register_settings() {
		$shop_title = ( new Option_Checkbox( 'shop_title', 'Show shop page title', 1 ) )
			->describe( 'Changes the page title to use your page title instead of Products' );
		if ( $shop_title->get() ) {
			add_filter( 'post_type_archive_title', [ $this, 'show_shop_page_title' ], 10, 2 );
		}

		$breadcrumbs = new Option_Checkbox( 'remove_breadcrumbs', 'Remove breadcrumbs', 0 );
		if ( $breadcrumbs->get() ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}

		$sku = new Option_Checkbox( 'remove_sku', 'Disable SKU stuff', 1 );
		if ( $sku->get() ) {
			add_filter( 'wc_product_sku_enabled', '__return_false' );
		}

		$hide_category = ( new Option_Checkbox( 'hide_category', 'Hide product categories and tags', 1 ) )
			->describe( 'Removes product categories and tags from being shown to people on the site' );
		if ( $hide_category->get() ) {
			add_filter( 'get_the_terms', [ $this, 'hide_product_tags' ], 10, 3 );
		}

		$use_user_names = ( new Option_Checkbox( 'use_user_names', 'Use usernames for billing', 1 ) )
			->describe( 'Replaces any billing details with info from user accounts' );
		if ( $use_user_names->get() ) {
			add_filter( 'woocommerce_before_customer_object_save', [ $this, 'prevent_customer_name_change' ] );
			add_filter( 'woocommerce_before_order_object_save', [ $this, 'prevent_order_name_change' ] );
		}

		// Adds all these settings.
		$this->admin->add_section( 'woo', 'WooCommerce' );
		/** @var \Transgression\Admin\Option[] */
		$settings = [
			$shop_title,
			$breadcrumbs,
			$sku,
			$hide_category,
			$use_user_names,
		];
		foreach ( $settings as $setting ) {
			$this->admin->add_setting( $setting->in_section( 'woo' ) );
		}
	}

	/**
	 * Skips the cart page
	 */
	public function skip_cart() {
		if ( ! is_cart() ) {
			return;
		}
		$target = WC()->cart->is_empty()
			? wc_get_page_permalink( 'shop' )
			: wc_get_checkout_url();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Clears the when a query var `empty_cart=yes` is passed
	 */
	public function clear_cart() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_checkout() || empty( $_GET['empty_cart'] ) || $_GET['empty_cart'] !== 'yes' ) {
			return;
		}

		WC()->cart->empty_cart();
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	/**
	 * Skips processing status as it doesn't matter
	 *
	 * @param int $order_id The order ID
	 * @return void
	 */
	public function skip_processing( int $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		$order->update_status( 'completed' );
	}

	/**
	 * Prevents adding variations of the same event to the cart
	 *
	 * @param bool $is_valid Filter value
	 * @param int $product_id The product ID
	 * @return bool
	 */
	public function prevent_variation_dupes( bool $is_valid, int $product_id ): bool {
		if ( ! $is_valid ) {
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

	/**
	 * Prevents people from buying a ticket to the same event during the checkout process.
	 *
	 * @return void
	 */
	public function prevent_checkout_dupes() {
		$cart_contents = WC()->cart->get_cart_contents();
		foreach ( $cart_contents as $cart_product ) {
			if (
				isset( $cart_product['product_id'] ) &&
				wc_customer_bought_product( '', get_current_user_id(), absint( $cart_product['product_id'] ) )
			) {
				wc_add_notice( 'You have already purchased a ticket to this event.', 'error' );
			}
		}
	}

	/**
	 * Settings
	 */
	public function show_shop_page_title( string $page_title, string $post_type ): string {
		if ( $post_type !== 'product' ) {
			return $page_title;
		}

		$page_id = wc_get_page_id( 'shop' );
		return get_the_title( $page_id );
	}

	/**
	 * Hides Woo taxonomy on the frontend
	 *
	 * @param mixed $terms
	 * @param int $post_id
	 * @param string $taxonomy
	 * @return array
	 */
	public function hide_product_tags( mixed $terms, int $post_id, string $taxonomy ): array {
		if ( is_admin() || ( $taxonomy !== 'product_cat' && $taxonomy !== 'product_cat' ) ) {
			return $terms;
		}
		return [];
	}

	/**
	 * Filters the customer's name to not deadname people
	 *
	 * @param \WC_Customer $customer
	 */
	public function prevent_customer_name_change( \WC_Customer $customer ) {
		$user = get_user_by( 'id', $customer->get_id() );
		if ( ! $user ) {
			return;
		}

		$customer->set_first_name( $user->first_name );
		$customer->set_billing_first_name( $user->first_name );
		$customer->set_shipping_first_name( $user->first_name );
		$customer->set_last_name( $user->last_name );
		$customer->set_billing_last_name( $user->last_name );
		$customer->set_shipping_last_name( $user->last_name );
	}

	/**
	 * Prevents changing the name from the user's on orders
	 *
	 * @param \WC_Order $order
	 * @return void
	 */
	public function prevent_order_name_change( \WC_Order $order ) {
		$user_id = absint( $order->get_customer_id() );
		if ( ! $user_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$order->set_billing_first_name( $user->first_name );
		$order->set_shipping_first_name( $user->first_name );
		$order->set_billing_last_name( $user->last_name );
		$order->set_shipping_last_name( $user->last_name );
	}

	/**
	 * Shows the login button if people aren't logged in
	 *
	 * @return void
	 */
	public function add_login_button(): void {
		if ( is_user_logged_in() ) {
			woocommerce_single_variation_add_to_cart_button();
		} else {
			load_view( 'login-form' );
		}
	}

	/**
	 * Saves the start and end time of an event
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function save_event_times( int $post_id ): void {
		$start_time = get_safe_post( 'start_time' );
		$end_time = get_safe_post( 'end_time' );
		if ( ! $start_time || ! $end_time ) {
			return;
		}

		check_admin_referer( "event-time-{$post_id}", '_trans_event_nonce' );
		update_post_meta( $post_id, 'start_time', ( new DateTimeImmutable( $start_time, wp_timezone() ) )->format( DATE_W3C ) );
		update_post_meta( $post_id, 'end_time', ( new DateTimeImmutable( $end_time, wp_timezone() ) )->format( DATE_W3C ) );
	}

	/**
	 * Adds meta boxes
	 *
	 * @return void
	 */
	public function meta_boxes(): void {
		add_meta_box(
			prefix( 'woo_event_time' ),
			'Event time',
			[ $this, 'render_time_metabox' ],
			'product',
			'side'
		);
	}

	/**
	 * Renders metabox to indicate the times for the event
	 *
	 * @param \WP_Post $post
	 * @return void
	 */
	public function render_time_metabox( \WP_Post $post ): void {
		$params = [
			'post_id' => $post->ID,
			'start_time' => $post->start_time,
			'end_time' => $post->end_time,
		];
		load_view( 'meta-event-time', $params );
	}

	/**
	 * Adds new columns in order admin screen
	 *
	 * @param array $columns Original columns
	 * @return array
	 */
	public function filter_admin_order_columns( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;
			if ( $column_name === 'order_date' ) {
				$new_columns['order_product'] = __( 'Product Name', 'transgression' );
			} elseif ( $column_name === 'order_total' ) {
				$new_columns['order_method'] = __( 'Payment Method', 'transgression' );
			}
		}
		return $new_columns;
	}

	/**
	 * Prints rows in order admin screen
	 *
	 * @param string $column
	 * @return void
	 */
	public function admin_order_custom_columns( string $column ) {
		$order = wc_get_order( get_the_ID() );
		if ( $column === 'order_product' ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item instanceof \WC_Order_Item_Product ) {
					printf(
						'<a href="%2$s">%1$s</a>',
						esc_html( $item->get_name() ),
						esc_url( get_edit_post_link( $item->get_product_id() ) )
					);
				}
			}
		} elseif ( $column === 'order_method' ) {
			$payment_gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : [];
			$payment_method = $order->get_payment_method();
			$payment_text = $order->get_payment_method_title() ?: __( 'Manual', 'transgression' );
			$url = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_transaction_url( $order ) : false;
			if ( $url ) {
				printf(
					'<a href="%3$s" title="%2$s">%1$s</a>',
					esc_html( $payment_text ),
					esc_attr( $payment_method ),
					esc_url( $url )
				);
			} else {
				printf(
					'<span title="%2$s">%1$s</span>',
					esc_html( $payment_text ),
					esc_attr( $payment_method )
				);
			}
		}
	}

	/**
	 * Gets gross sales for a given product
	 *
	 * @param int $product_id
	 * @return float
	 */
	public static function get_gross_sales( int $product_id ): float {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(product_gross_revenue)
			FROM {$wpdb->prefix}wc_order_product_lookup op
			INNER JOIN {$wpdb->prefix}wc_order_stats os ON op.order_id = os.order_id
			WHERE op.product_id = %d AND os.status = 'wc-completed'",
			$product_id
		) );
		return floatval( $value );
	}
}
);
	}
}
