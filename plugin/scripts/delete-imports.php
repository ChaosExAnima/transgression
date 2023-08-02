<?php // phpcs:disable NeutronStandard.StrictTypes.RequireStrictType, WordPress.DB.SlowDBQuery

use function Transgression\Scripts\check_wet_run;

require_once __DIR__ . '/helper.php';

$wet_run = check_wet_run();

$users = new WP_User_Query( [
	'meta_key' => '_imported',
] );

if ( $users->get_total() === 0 ) {
	WP_CLI::warning( 'No users found, exiting' );
	exit;
}

$errors = 0;
$updated = 0;

/** @var WP_User $user */
foreach ( $users->get_results() as $user ) {
	if ( $wet_run ) {
		if ( wp_delete_user( $user->ID, 2 ) ) {
			++$updated;
		} else {
			WP_CLI::warning( "Could not delete {$user->display_name} ({$user->ID})" );
			++$errors;
		}
	} else {
		++$updated;
	}
	WP_CLI::line( "Deleted user {$user->display_name} ({$user->ID})" );
}

WP_CLI\Utils\report_batch_operation_results(
	'user',
	'delete',
	$users->get_total(),
	$updated,
	$errors
);
