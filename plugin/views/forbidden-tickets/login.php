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
$error_code = absint( $params['error_code'] );
$tickets_url = $params['tickets_url'];

load_view( 'forbidden-tickets/messages', [ 'code' => $error_code ] );
?>
<aside class="tickets-login">
	<?php if ( ! is_user_logged_in() ) : ?>
		<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
			<div class="flex">
				Buy tickets with:
				<?php do_action( 'transgression_social_login', true ); ?>
			</div>
		<?php endif; ?>
		<label for="tickets-email">Send your code to your email:</label>
		<form action="<?php echo esc_url( get_current_url() ); ?>" method="post" class="flex">
			<input
				type="email"
				id="tickets-email"
				name="tickets-email"
				placeholder="Email"
				value="<?php echo esc_attr( get_safe_post( 'tickets-email' ) ); ?>"
				required
			/>
			<button type="submit" class="button">
				Get tickets
			</button>
		</form>
	<?php else : ?>
		<p>
			Welcome, <?php echo esc_html( wp_get_current_user()->display_name ); ?>!
			Your code to unlock tickets is:
		</p>
		<a href="<?php echo esc_url( $tickets_url ); ?>" class="buy">
			<code><?php echo esc_html( $code ); ?></code>
		</a>
		<p>Remember, do not share this with anyone else!</p>
	<?php endif; ?>
</aside>
