<?php declare( strict_types = 1 );

namespace Transgression\Scripts;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Script files only' );
}

if ( empty( $args ) || count( $args ) !== 1 ) {
	WP_CLI::error( 'Need path to CSV file' );
}

$path = $args[0];
$ext = pathinfo( $path, PATHINFO_EXTENSION );
if ( $ext !== 'csv' ) {
	WP_CLI::error( 'CSV only' );
}
if ( ! file_exists( $path ) ) {
	WP_CLI::error( 'Could not find file' );
}
if ( ! is_readable( $path ) ) {
	WP_CLI::error( 'File is not readable' );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$file = file_get_contents( $path );
$lines = explode( "\n", $file );
WP_CLI::debug( 'Got ' . count( $lines ) . ' lines' );

$found = 0;
$created = 0;

foreach ( array_slice( $lines, 1 ) as $row ) {
	[$email, $skip, $name, $pronouns] = array_map( 'trim', str_getcsv( $row ) );
	$user_id = email_exists( $email );
	if ( $user_id ) {
		WP_CLI::line( "Found {$email} as user {$user_id}" );
		++$found;
	} else {
		WP_CLI::line( "Creating {$name} ({$pronouns}) at {$email}" );
		++$created;
	}
}

WP_CLI::success( "Users imported! Created {$created} and found {$found}" );
