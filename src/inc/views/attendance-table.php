<?php declare( strict_types = 1 );
/** @var WC_Product[] */
$products = $params['products'];
/** @var int */
$product_id = $params['product_id'];
/** @var WC_Order[] */
$orders = $params['orders'];
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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

	<table>
		<thead>
			<tr>
				<th>Order</th>
				<th>Photo</th>
				<th>Name</th>
				<th>Email</th>
				<th>Volunteering</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $orders as $order ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $order['id'] ) ); ?>">
							#<?php echo esc_html( $order['id'] ); ?>
						</a>
					</td>
					<td><?php echo get_avatar( $order['user_id'] ); ?></td>
					<td>
						<a href="<?php echo esc_url( get_edit_user_link( $order['user_id'] ) ); ?>">
							<?php echo esc_html( $order['name'] ); ?>
						</a>
					</td>
					<td><?php echo esc_html( strtolower( $order['email'] ) ); ?></td>
					<td><?php echo $order['volunteer'] ? '✔️' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
