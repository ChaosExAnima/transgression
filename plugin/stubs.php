<?php declare( strict_types = 1 );
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

/**
 * Stub file
 * Plugin functions that aren't namespaced, declared here so I don't have to write `function_exists` all the time.
 */

if ( ! function_exists( 'jetpack_photon_url' ) ) {
	/**
	 * Stub for jetpack URL. Mostly for the IDE to stop complaining.
	 *
	 * @param string $url The URL to use.
	 * @param array $args
	 * @return string
	 */
	function jetpack_photon_url( string $url, array $args = [] ): string {
		return $url;
	}
}
