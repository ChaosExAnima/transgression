<?php // phpcs:ignore NeutronStandard.StrictTypes.RequireStrictTypes

use function Transgression\Scripts\check_wet_run;

require_once __DIR__ . '/helper.php';

$wet_run = check_wet_run();

$user_query = new WP_User_Query( [
	'role' => 'customer',
	'orderby' => 'ID',
] );
/** @var WP_User[]  */
$users = $user_query->get_results();

add_filter( 'send_password_change_email', '__return_false' );
add_filter( 'send_email_change_email', '__return_false' );

$errors = 0;
$updated = 0;
$skipped = 0;

foreach ( $users as $user ) {
	$user_id = $user->ID;
	$name = $user->display_name;
	$pronouns = $user->pronouns ?? '';

	// Check the application first
	$application_id = $user->application;
	if ( $application_id ) {
		$application = get_post( $application_id );
		if ( ! $application ) {
			WP_CLI::warning( "Application ID {$application_id} is not found for user {$user_id}" );
			++$errors;
			continue;
		}
		$name = trim( $application->post_title );
		$pronouns = trim( $application->pronouns );
	} else {
		WP_CLI::debug( "User {$user_id} has no application" );
	}

	// Strip parts of the name that we don't want
	$matches = [];
	if ( preg_match( '/^([^\/\(]+)/', $name, $matches ) ) {
		if ( $name !== $matches[1] ) {
			$name = trim( $matches[1] );
		}
	}
	$name = preg_replace( '/ {2,}/', ' ', $name );

	if ( ! $name ) {
		++$errors;
		WP_CLI::warning( "User {$user_ID} has no name set" );
		continue;
	}

	// Extract first and last name
	/** @var string[] */
	$name_parts = array_map( 'trim', explode( ' ', $name, 2 ) );
	$first_name = $name_parts[0];
	$last_name = '';
	if ( count( $name_parts ) === 2 && $name_parts[1] ) {
		$last_name = $name_parts[1];
	}

	$fields = [
		'display_name' => $name,
		'first_name' => $first_name,
		'last_name' => $last_name,
		'nickname' => $name,
		'pronouns' => strtolower( $pronouns ),
		'user_login' => $user->user_email,
	];
	$changed = [];
	foreach ( $fields as $field_name => $field_value ) {
		if ( $user->{$field_name} === $field_value ) {
			continue;
		}
		if ( ! $user->{$field_name} ) {
			WP_CLI::debug( "User {$user_id} has no {$field_name} set" );
		} else {
			WP_CLI::debug( "User {$user_id} {$field_name} is '{$user->{$field_name}}' vs {$field_value}" );
		}
		$changed[] = $field_name;
	}

	$count = count( $changed );
	if ( $count ) {
		$fields['ID'] = $user_id;
		$result = wp_update_user( $fields );
		if ( is_wp_error( $result ) ) {
			++$errors;
			WP_CLI::warning( "User {$user_id} had an error updating: {$result->get_error_message()}" );
		} else {
			++$updated;
			WP_CLI::line( "User {$user_id} has {$count} changed fields: " . implode( ', ', $changed ) );
		}
	} else {
		++$skipped;
	}
}

WP_CLI\Utils\report_batch_operation_results(
	'user',
	'updated',
	count( $users ),
	$updated,
	$errors,
	$skipped
);
