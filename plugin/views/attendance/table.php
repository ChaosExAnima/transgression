<?php declare( strict_types = 1 );
/** @var WC_Product[] */
$products = $params['products'];
/** @var int */
$product_id = $params['product_id'];
/** @var WC_Order[] */
$orders = $params['orders'];
?>
<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get">
	<input type="hidden" name="page" value="transgression_attendance" />
	<p>
		<label for="product">Event</label>
		<select name="product_id" id="product">
			<?php foreach ( $products as $product ) : ?>
				<option value="<?php echo absint( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>>
					<?php echo esc_html( $product->get_title() ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button button-primary">Update</button>
		<button type="button" onclick="window.print()" class="button">Print</button>
	</p>
</form>

<table class="attendance">
	<thead>
		<tr>
			<th>Order</th>
			<th class="pic">Photo</th>
			<th>Name</th>
			<th>Email</th>
			<th>Volunteer</th>
			<th>Vaccinated</th>
			<th>Test Checked</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $orders as $order ) : ?>
			<tr>
				<td class="order">
					<a href="<?php echo esc_url( get_edit_post_link( $order['id'] ) ); ?>">
						#<?php echo esc_html( $order['id'] ); ?>
					</a>
				</td>
				<td class="pic">
					<?php if ( $order['pic'] ) : ?>
						<img src="<?php echo esc_url( $order['pic'] ); ?>" loading="lazy" width="96" height="96" />
					<?php else : ?>
						<em>None on record</em>
					<?php endif; ?>
				</td>
				<td class="name">
					<a href="<?php echo esc_url( get_edit_user_link( $order['user_id'] ) ); ?>">
						<?php echo esc_html( $order['name'] ); ?>
					</a>
				</td>
				<td class="email"><?php echo esc_html( strtolower( $order['email'] ) ); ?></td>
				<td class="volunteer check"><?php echo $order['volunteer'] ? '✔️' : ''; ?></td>
				<td class="vaccine check"><?php echo $order['vaccine'] ? '✔️' : ''; ?></td>
				<td class="test check"></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
