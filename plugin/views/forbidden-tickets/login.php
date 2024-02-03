<?php declare( strict_types=1 );

/**
 * Template for rendering the login view.
 * @var array $params
 */

namespace Transgression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$code = $params['code'];
$tickets_url = $params['tickets_url'];
?>
<aside class="tickets-login">
	<?php if ( ! is_user_logged_in() ) : ?>
		<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
			<div class="flex">
				Buy tickets with:
				<?php do_action( 'transgression_social_login', true ); ?>
			</div>
		<?php endif; ?>
		<label for="login-email">Send your code to your email:</label>
		<form action="" method="post" class="flex">
			<input type="email" id="login-email" name="login-email" placeholder="Email" required />
			<button type="submit" class="button">
				Get tickets
			</button>
		</form>
	<?php else : ?>
		<p>
			Welcome, <?php echo esc_html( wp_get_current_user()->display_name ); ?>!
			Your code to unlock tickets is:
		</p>
		<code><?php echo esc_html( $code ); ?></code>
		<p>Remember, do not share this with anyone else!</p>
	<?php endif; ?>
</aside>
