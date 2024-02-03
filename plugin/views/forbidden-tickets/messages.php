<?php declare( strict_types=1 );

/**
 * Template for rendering the login view.
 * @var array $params
 */

namespace Transgression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$code = absint( $params['code'] );

if ( ! $code ) {
	return;
}

$level = 'error';
$message = 'Unknown error. Please try again.';

switch ( $code ) {
	case 101:
		$message = 'Invalid social login. Please try again.';
		break;
	case 102:
	case 103:
	case 104:
		$message = 'There was a problem with your login.';
		break;
	case 105:
	case 202:
		$message = 'We donʼt know this email. Did you apply with another one?';
		break;
	case 200:
		$message = 'We sent you an email with your ticket code.';
		$level = 'success';
		break;
	case 201:
		$message = 'Invalid email. Please try again.';
		break;
}

$email = sprintf(
	'mailto:%s?subject=Cannot get tickets (code %d)',
	get_bloginfo( 'admin_email' ),
	$code
);

?>

<div class="message message-<?php echo esc_attr( $level ); ?>">
	<?php echo esc_html( $message ); ?>
	<?php if ( $level !== 'success' ) : ?>
		<a href="<?php echo esc_url( $email ); ?>" target="_blank">
			Click here to get help
		</a>
	<?php endif; ?>
</div>
