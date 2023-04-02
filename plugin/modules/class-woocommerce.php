<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Option_Checkbox;
use Transgression\Admin\Page;

use function Transgression\load_view;

class WooCommerce extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'woocommerce/woocommerce.php' ];

	public function __construct( protected Page $settings_page ) {
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

		// Adds all these settings.
		$this->settings_page->add_section( 'woo', 'WooCommerce' );
		/** @var \Transgression\Admin\Option[] */
		$settings = [
			$shop_title,
			$breadcrumbs,
			$sku,
			$hide_category,
		];
		foreach ( $settings as $setting ) {
			$this->settings_page->add_setting( $setting->in_section( 'woo' ) );
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
			FROM {$wpdb->prefix}wc_order_product_lookup
			WHERE product_id = %d",
			$product_id
		) );
		return floatval( $value );
	}
}
