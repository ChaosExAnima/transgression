<?php declare( strict_types=1 );

namespace Transgression;

const KSES_TAGS = [
	'a' => [
		'target' => [],
		'href' => [],
		'rel' => [],
	],
	'em' => [],
	'strong' => [],
];

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
function load_view( string $view, array $params = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
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
 * Renders text with line breaks as br tags
 *
 * @param string $text
 * @return void
 */
function render_lines( string $text ): void {
	$lines = array_map( 'trim', explode( "\n", trim( $text ) ) );
	foreach ( $lines as $index => $line ) {
		if ( $index !== 0 ) {
			echo '<br />';
		}
		echo wp_kses( linkify( $line ), KSES_TAGS );
	}
}

/**
 * Links provided text
 *
 * @param string $text
 * @return string
 */
function linkify( string $text ): string {
	$text = preg_replace(
		'/(https?:\/\/[^ \)\n]+)/i',
		'<a href="$1" target="_blank" rel="noreferrer">$1</a>',
		$text
	);
	$text = preg_replace(
		'/@ ?([a-z0-9\.-_]+)/i',
		'<a href="https://instagram.com/$1" target="_blank" rel="noreferrer">@$1</a>',
		$text
	);
	return $text;
}

/**
 * Renders a date and time, optionally relatively.
 *
 * @param integer|string $date
 * @param string|null $class_name
 * @param boolean $relative
 * @return void
 */
function render_time( int|string $date, ?string $class_name = null, bool $relative = true ) {
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
		'<time datetime="%3$s" title="%2$s" class="%4$s">%1$s</time>',
		esc_html( $text ),
		esc_attr( formatted_date( $timestamp ) ),
		esc_attr( wp_date( 'c', $timestamp ) ),
		esc_attr( $class_name ?? '' )
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
