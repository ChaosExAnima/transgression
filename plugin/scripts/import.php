<?php // phpcs:ignore NeutronStandard.StrictTypes.RequireStrictTypes

use function Transgression\Scripts\check_wet_run;

require_once __DIR__ . '/helper.php';

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

$wet_run = check_wet_run();

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$file = file_get_contents( $path );
$lines = explode( "\n", $file );

$found = 0;
$errors = 0;
$created = 0;

foreach ( array_slice( $lines, 1 ) as $row ) {
	[$email, $skip, $name, $pronouns] = array_map( 'trim', str_getcsv( $row ) );
	if ( ! $email || ! is_email( $email ) ) {
		WP_CLI::warning( "Error creating user: '{$email}' is invalid" );
		++$errors;
		continue;
	} elseif ( ! $name ) {
		WP_CLI::warning( 'Error creating user: name is empty' );
		++$errors;
		continue;
	}

	$user_id = email_exists( $email );
	if ( $user_id ) {
		WP_CLI::line( "Found {$email} as user {$user_id}" );
		++$found;
	} else {
		$user_id = -1;
		if ( $wet_run ) {
			$user_meta = [
				'nickname' => $name,
				'first_name' => $name,
				'pronouns' => $pronouns,
				'app_extra' => 'rabbit hole',
				'_imported' => time(),
			];
			$user_id = wp_insert_user( [
				'role' => 'customer',
				'user_pass' => wp_generate_password( 100 ),
				'user_login' => $email,
				'user_email' => $email,
				'display_name' => $name,
				'meta_input' => $user_meta,
			] );
		}

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::warning( "Error creating user {$email}: {$user_id->get_error_message()}" );
			++$errors;
		} else {
			WP_CLI::line( "Created {$name} ({$pronouns}) at {$email} with user ID {$user_id}" );
			++$created;
		}
	}
}

WP_CLI\Utils\report_batch_operation_results(
	'user',
	'imported',
	count( $lines ) - 1,
	$created,
	$errors,
	$found
);
