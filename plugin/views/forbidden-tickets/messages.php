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
		$message = 'There is no account associated with the email you provided. Is this this correct one?';
		break;
}

?>

<div class="message message-<?php echo esc_attr( $level ); ?>">
	<?php echo esc_html( $message ); ?>
</div>
