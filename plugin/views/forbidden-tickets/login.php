<?php declare( strict_types=1 );

/**
 * Template for rendering the login view.
 * @var array $params
 */

namespace Transgression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="woocommerce-variation-add-to-cart variations_button">
	<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
	<p>
		Buy tickets with <?php do_action( 'transgression_social_login' ); ?>
	</p>
	<?php endif; ?>
	<label for="login-email">Send a ticket to your email:</label>
	<p>
		<input type="email" id="login-email" name="login-email" placeholder="Email" required class="shop-login input-text" />
	</p>
	<button type="submit" class="single_add_to_cart_button button alt">
		Log in
	</button>
</div>
