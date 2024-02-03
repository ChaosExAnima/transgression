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
		$message = 'We donʼt recognize this email. Did you use a different one?';
		break;
}

?>

<div class="message message-<?php echo esc_attr( $level ); ?>">
	<?php echo esc_html( $message ); ?>
	<a href="mailto:<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>?subject=Cannot get tickets (code <?php echo absint( $code ); ?>)">
		Click here to get help
	</a>
</div>
