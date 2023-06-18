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
?>
<form action="<?php echo esc_url( admin_url() ); ?>" method="get">
	<?php wp_nonce_field( prefix( 'person_search' ) ); ?>
	<label for="person-search">Search for someone:</label>
	<input value="<?php echo esc_attr( $query ); ?>" type="search" name="person_search" list="person-search-emails" />
	<button type="submit" class="button">Search</button>
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
					<a href="<?php echo esc_url( get_edit_user_link( $person->user->id ) ); ?>">
						User
					</a>
				<?php endif; ?>
				<?php if ( $person->customer && $person->customer->get_order_count() > 0 ) : ?>
					&ndash;
					<a href="<?php echo esc_url( $person->orders_link() ); ?>">
						<?php
						echo esc_html( sprintf(
							'%s %s ($%s)',
							$person->customer->get_order_count(),
							_n( 'order', 'orders', $person->customer->get_order_count(), 'transgression' ),
							wc_format_decimal( $person->customer->get_total_spent(), 0, true )
						) );
						?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</form>
