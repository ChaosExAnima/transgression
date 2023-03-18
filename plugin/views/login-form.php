<?php declare( strict_types=1 );
/**
 * Template for letting users log in
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="woocommerce-variation-add-to-cart variations_button">
	<label for="login-email">Enter your email to log in:</label>
	<p>
		<input type="email" id="login-email" name="login-email" placeholder="Email" required class="shop-login input-text" />
	</p>
	<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
	<p>
		Or log in here: <?php do_action( 'transgression_social_login' ); ?>
	</p>
	<?php endif; ?>
	<button type="submit" class="single_add_to_cart_button button alt">
		Log in
	</button>
</div>
