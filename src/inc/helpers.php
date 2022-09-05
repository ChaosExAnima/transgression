<?php declare( strict_types=1 );

namespace Transgression;

/**
 * Returns a locale-formatted date string.
 *
 * @param integer|string $date
 * @param bool $with_time
 * @return string
 */
function formatted_date( int|string $date, bool $with_time = true ): string {
	if ( is_int( $date ) ) {
		$timestamp = $date;
	} else {
		$timestamp = strtotime( $date );
	}
	$format = get_option( 'date_format' );
	if ( $with_time ) {
		$format .= ' ' . get_option( 'time_format' );
	}
	return wp_date( $format, $timestamp );
}

/**
 * Renders a date and time, optionally relatively.
 *
 * @param integer|string $date
 * @param boolean $relative
 * @return void
 */
function render_time( int|string $date, bool $relative = false ) {
	if ( is_int( $date ) ) {
		$timestamp = $date;
	} else {
		$timestamp = strtotime( $date );
	}

	if ( $relative ) {
		$text = human_time_diff( $timestamp ) . ' ago';
	} else {
		$text = formatted_date( $timestamp );
	}
	printf(
		'<time datetime="%3$s" title="%2$s" class="app-details">%1$s</time>',
		esc_html( $text ),
		esc_attr( formatted_date( $timestamp ) ),
		esc_attr( wp_date( 'c', $timestamp ) )
	);
}

/**
 * Gets the current URL.
 *
 * @return string
 */
function get_current_url(): string {
	return esc_url_raw( home_url( $_SERVER['REQUEST_URI'] ) );
}

/**
 * Checks if a string is a URL or not.
 *
 * @see https://wordpress.stackexchange.com/questions/166392/how-to-check-if-a-string-is-a-valid-url
 * @param string $url
 * @return boolean
 */
function is_url( string $url ): bool {
	if ( $url && strtolower( esc_url_raw( $url ) ) === strtolower( $url ) ) {
		return true;
	} else {
		return false;
	}
}

function strip_query( string $url ): string {
	$result = strtok( $url, '?' );
	if ( false === $result ) {
		log_error( "Was provided invalid URL: $url" );
		return $url;
	}
	return $result;
}
