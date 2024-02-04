<?php declare( strict_types = 1 );
/**
 * Template for dashboard user search widget
 * @var array $params
 */

namespace Transgression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string */
$query = $params['query'];
/** @var Person[] */
$people = $params['people'];
/** @var Modules\ForbiddenTickets */
$tickets = $params['tickets'];
?>
<form action="<?php echo esc_url( admin_url() ); ?>" method="get">
	<?php wp_nonce_field( prefix( 'person_search' ) ); ?>
	<label for="person-search">Search for someone:</label>
	<input value="<?php echo esc_attr( $query ); ?>" type="search" name="person_search" list="person-search-emails" />
	<button type="submit" class="button">Search</button>
	<p class="description" style="margin-top: 1rem;">
		Prefixes:
		<code>ig:</code> to look for Instagram accounts,
		<code>conf:</code> to search conflicts,
		<code>access:</code> for accessibility,
		<code>extra:</code> for extra
	</p>
	<ul>
		<?php foreach ( $people as $person ) : ?>
			<li>
				<?php echo esc_html( $person->name() ); ?>
				<?php if ( $person->application ) : ?>
					&ndash;
					<a href="<?php echo esc_url( get_edit_post_link( $person->application->ID ) ); ?>">
						Application
						<?php echo $person->approved() ? '✅' : '❌'; ?>
					</a>
				<?php endif; ?>
				<?php if ( $person->user ) : ?>
					&ndash;
					<a href="<?php echo esc_url( get_edit_user_link( $person->user_id() ) ); ?>">
						User
					</a>
					&ndash;
					<a href="<?php echo esc_url( $tickets->user_ticket_url( $person->user_id() ) ); ?>" target="_blank">
						<code>
							<?php echo esc_html( $tickets->get_code( $person->user_id() ) ); ?>
						</code>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</form>
