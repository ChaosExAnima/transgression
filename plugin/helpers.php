<?php declare( strict_types=1 );

namespace Transgression;

/**
 * Gets the current URL.
 *
 * @return string
 */
function get_current_url(): string {
	return esc_url_raw( home_url( $_SERVER['REQUEST_URI'] ) );
}

function strip_query( string $url ): string {
	$result = strtok( $url, '?' );
	if ( false === $result ) {
		return $url;
	}
	return $result;
}
