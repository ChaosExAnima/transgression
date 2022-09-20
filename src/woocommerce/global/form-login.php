<?php declare( strict_types=1 );
/**
 * Login form
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( is_user_logged_in() ) {
	return;
}

?>
<form
	class="woocommerce-form woocommerce-form-login login"
	method="post"
	<?php echo ( $hidden ) ? 'style="display:none;"' : ''; ?>
>

	<?php do_action( 'woocommerce_login_form_start' ); ?>

	<?php echo ( $message ) ? wpautop( wptexturize( $message ) ) : ''; // @codingStandardsIgnoreLine ?>

	<p class="form-row woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="login-email"><?php esc_html_e( 'Enter your email', 'woocommerce' ); ?></label>
		<input type="text" class="input-text" name="login-email" id="login-email" autocomplete="email" required />
	</p>
	<div class="clear"></div>

	<?php do_action( 'woocommerce_login_form' ); ?>

	<p class="form-row">
		<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
		<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>" />
		<button
			type="submit"
			class="woocommerce-button button woocommerce-form-login__submit"
			name="login"
			value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"
		><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>
	</p>

	<div class="clear"></div>

	<?php do_action( 'woocommerce_login_form_end' ); ?>

</form>
