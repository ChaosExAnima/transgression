<?php // phpcs:disable NeutronStandard.StrictTypes.RequireStrictType, WordPress.DB.SlowDBQuery

use Transgression\Modules\ForbiddenTickets;

use const Transgression\PLUGIN_SLUG;

use function Transgression\Scripts\check_wet_run;

require_once __DIR__ . '/helper.php';

$wet_run = check_wet_run();

$users = new WP_User_Query( [
	'meta_query' => [
		'key' => ForbiddenTickets::USER_CODE_KEY,
		'compare' => 'NOT EXISTS',
	],
] );

if ( $users->get_total() === 0 ) {
	WP_CLI::warning( 'No users found, exiting' );
	exit;
}

$errors = 0;
$updated = 0;

$codes = [];

$tickets = new ForbiddenTickets();

// Generated de-duplicated codes
$existing_codes = $tickets->get_all_codes();
$new_codes = [];
$code_count = 0;
$needed_count = $users->get_total();
while ( $code_count < $needed_count ) {
	$code = $tickets->generate_code( false );
	if ( ! in_array( $code, $existing_codes, true ) ) {
		$new_codes[] = $code;
		++$code_count;
	}
}

/** @var WP_User $user */
foreach ( $users->get_results() as $user ) {
	$code = array_pop( $new_codes );
	if ( $wet_run ) {
		if ( update_user_meta( $user->ID, ForbiddenTickets::USER_CODE_KEY, $code ) ) {
			++$updated;
		} else {
			WP_CLI::warning( "Could not delete {$user->display_name} ({$user->ID})" );
			++$errors;
		}
	} else {
		++$updated;
	}
	WP_CLI::line( "Generated code {$code} for user {$user->display_name} ({$user->ID})" );
}

// Clear the cache
if ( $wet_run ) {
	wp_cache_delete( ForbiddenTickets::CACHE_ALL_KEY, PLUGIN_SLUG );
}

WP_CLI\Utils\report_batch_operation_results(
	'code',
	'generate',
	$users->get_total(),
	$updated,
	$errors
);
