<?php declare( strict_types=1 );

namespace Transgression;

/**
 * Gets a URL to a file in the assets folder
 *
 * @param string $file
 * @return string
 */
function get_asset_url( string $file ): string {
	return plugin_dir_url( __FILE__ ) . 'assets/' . untrailingslashit( $file );
}

/**
 * Loads a view
 *
 * @param string $view
 * @param array $params
 * @return void
 */
function load_view( string $view, array $params = [] ) {
	$path = PLUGIN_ROOT . "/views/{$view}.php";
	if ( ! file_exists( $path ) ) {
		Logger::error( new \Error( "Could not find view with path {$path}" ) );
		return;
	}

	include $path;
}

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
	// phpcs:ignore WordPress.Security
	return esc_url_raw( home_url( $_SERVER['REQUEST_URI'] ?? '' ) );
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
		return $url;
	}
	return $result;
}

/**
 * Inserts an array at a specific location, preserving keys.
 *
 * @param array $source
 * @param array $insert
 * @param int $offset
 * @return array
 */
function insert_in_array( array $source, array $insert, int $offset = 0 ): array {
	return array_slice( $source, 0, $offset, true ) +
		$insert +
		array_slice( $source, $offset, null, true );
}
