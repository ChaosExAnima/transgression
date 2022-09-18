<?php declare( strict_types=1 );
/**
 * Login Form
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

do_action( 'woocommerce_before_customer_login_form' );

woocommerce_login_form();

do_action( 'woocommerce_after_customer_login_form' );
