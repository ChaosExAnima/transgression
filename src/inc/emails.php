<?php declare( strict_types=1 );

namespace Transgression;

use WP_Error;

function send_email( string $email, string $subject, string $template, array $params = [] ): ?WP_Error {
	ob_start();
	include theme_path( "/inc/emails/{$template}.php" );
	$body = ob_get_clean();
	$success = _send( $email, prefix_subject( $subject ), $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( !$success ) {
		return new WP_Error( 'email-failed', 'Could not send email', compact( 'email', 'subject', 'template', 'params' ) );
	}
	return null;
}

function send_user_email( int $user_id, string $subject, string $template, array $params = [] ): ?WP_Error {
	$user = get_userdata( $user_id );
	if ( !$user ) {
		return new WP_Error( 'email-no-user', 'User not found', compact( 'user_id' ) );
	}
	send_email( $user->user_email, $subject, $template, array_merge( $params, compact( 'user' ) ) );
	return null;
}

function prefix_subject( string $subject ): string {
	return get_bloginfo() . ": {$subject}";
}

function filter_from( string $from ): string {
	if ( !is_email( $from ) ) {
		return 'events@transgression.party';
	}
	return $from;
}
add_filter( 'wp_mail_from', cb( 'filter_from' ) );

function _send( string $email, string $subject, string $body, array $headers = [] ): bool {
	return wp_mail( $email, $subject, $body, $headers );
}
