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
<aside class="tickets-login">
	<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
		<div>
			Buy tickets with:
			<?php do_action( 'transgression_social_login', true ); ?>
		</div>
	<?php endif; ?>
	<label for="login-email">Send a ticket to your email:</label>
	<form action="" method="post">
		<input type="email" id="login-email" name="login-email" placeholder="Email" required />
		<button type="submit" class="button">
			Get tickets
		</button>
	</form>
</aside>
