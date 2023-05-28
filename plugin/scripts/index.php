<?php declare( strict_types = 1 );

namespace Transgression\Scripts;

// Exit if it's not CLI time
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Script files only' );
}

require_once __DIR__ . '/helper.php';

require_once __DIR__ . '/applications.php';
