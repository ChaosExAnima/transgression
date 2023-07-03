<?php declare( strict_types = 1 );
/**
 * Template for the attendance table
 * @var array $params
 */

/** @var WC_Order[] */
$orders = $params['orders'];
?>

<table class="attendance" id="attendance">
	<thead>
		<tr>
			<th>Order</th>
			<th class="pic">Photo</th>
			<th>Name</th>
			<th>Email</th>
			<th>Volunteer</th>
			<th>Vaccinated</th>
			<th>Checked In</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $orders as $order ) : ?>
			<tr data-order-id="<?php echo absint( $order['id'] ); ?>" id="order-<?php echo absint( $order['id'] ); ?>">
				<td class="order">
					<?php if ( current_user_can( 'edit_products' ) ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $order['id'] ) ); ?>">
							#<?php echo absint( $order['id'] ); ?>
						</a>
					<?php else : ?>
						#<?php echo absint( $order['id'] ); ?>
					<?php endif; ?>
				</td>
				<td class="pic">
					<?php if ( $order['pic'] ) : ?>
						<img src="<?php echo esc_url( $order['pic'] ); ?>" loading="lazy" width="96" height="96" />
					<?php else : ?>
						<em>None on record</em>
					<?php endif; ?>
				</td>
				<td class="name">
					<?php if ( current_user_can( 'edit_user' ) ) : ?>
						<a href="<?php echo esc_url( get_edit_user_link( $order['user_id'] ) ); ?>">
							<?php echo esc_html( $order['name'] ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $order['name'] ); ?>
					<?php endif; ?>
				</td>
				<td class="email"><?php echo esc_html( strtolower( $order['email'] ) ); ?></td>
				<td class="volunteer check"><?php echo $order['volunteer'] ? '✔️' : ''; ?></td>
				<td class="vaccinated check"><?php echo $order['vaccinated'] ? '✔️' : ''; ?></td>
				<td class="checked-in">
					<button class="button <?php echo ! $order['checked_in'] ? 'button-primary' : ''; ?>">
						<?php echo $order['checked_in'] ? 'Yes' : 'No'; ?>
					</button>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
