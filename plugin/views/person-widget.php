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
	<button type="submit">Search</button>
	<?php if ( count( $people ) === 1 ) :
		$person = $people[0];
		?>
		<div>
			<a href="<?php echo esc_url( get_edit_user_link( $person->user->id ) ); ?>">
				<?php if ( $person->image_url() ) : ?>
					<img src="<?php echo esc_url( $person->image_url() ); ?>" width="100" />
				<?php endif; ?>
				<h3><?php echo esc_html( $person->name() ); ?></h3>
			</a>
			<?php if ( $person->application ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $person->application->ID ) ); ?>">
					Application
				</a>
			<?php endif; ?>
			<?php if ( $person->customer ) : ?>
				<a href="<?php echo esc_url( $person->orders_link() ); ?>">
					<?php
					echo esc_html( sprintf(
						'%s %s',
						$person->customer->get_order_count(),
						_n( 'order', 'orders', $person->customer->get_order_count(), 'transgression' )
					) );
					?>
				</a>
			<?php endif; ?>
		</div>
	<?php elseif ( count( $people ) > 1 ) : ?>
		<ul>
			<?php foreach ( $people as $person ) : ?>
				<li>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( "/?person_search={$person->email()}" ), prefix( 'person_search' ) ) ); ?>">
						<?php echo esc_html( $person->name() ); ?> (<?php echo esc_html( $person->email() ); ?>)
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</form>
