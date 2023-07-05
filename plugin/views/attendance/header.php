<?php declare( strict_types = 1 );
/**
 * Template for attendance header
 * @var array $params
 */

namespace Transgression;

/** @var int current product id */
$product_id = $params['product_id'];
/** @var WC_Product[] list of all products */
$products = $params['products'];
/** @var string search string */
$search = $params['search'];
?>
<form id="header-form" class="header" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get">
	<input type="hidden" name="page" value="transgression_attendance" />
	<?php wp_nonce_field( prefix( 'attendance' ) ); ?>
	<p class="product-wrapper">
		<label for="product">Event</label>
		<select class="widefat" name="product_id" id="product">
			<?php foreach ( $products as $product ) : ?>
				<option value="<?php echo absint( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>>
					<?php echo esc_html( $product->get_title() ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button button-primary">Update</button>
		<button type="button" onclick="window.print()" class="button button-print">Print</button>
		<label for="percentage">Attending</label>
		<meter id="percentage" value="0" min="0">0 / 0</meter>
	</p>
	<p class="search-wrapper">
		<input
			id="search"
			name="search"
			type="search"
			class="widefat"
			value="<?php echo esc_attr( $search ); ?>"
			aria-label='Search input for attendees'
			spellcheck='false'
			incremental
			placeholder='Search for order id, name, or email'
			autocomplete="name email"
		/>
		<button type="submit" class="button button-primary">
			<span class="dashicons dashicons-search"></span>
		</button>
	</p>
</form>
