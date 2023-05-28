<?php declare( strict_types = 1 );

namespace Transgression\Scripts;

use WP_CLI;

// Exit if it's not CLI time
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Script files only' );
}

/**
 * Pauses the script to ask a question
 *
 * @param string $question The question text
 * @return string Answer
 */
function get_input( string $question ): string {
	WP_CLI::line( $question );
	return strtolower( trim( fgets( STDIN ) ) );
}

/**
 * Asks whether to do a wet run
 *
 * @return boolean
 */
function check_wet_run(): bool {
	$wet_run = 'y' === get_input( 'Do wet run? [y/N]' );
	if ( $wet_run ) {
		WP_CLI::warning( 'Doing wet run!' );
	}
	return $wet_run;
}
