<?php declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$attribute_keys = array_keys( $attributes );
$variations_json = wp_json_encode( $available_variations );
$variations_attr = function_exists( 'wc_esc_json' )
	? wc_esc_json( $variations_json )
	: _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );

$available_variations = $product->get_available_variations();

do_action( 'woocommerce_before_add_to_cart_form' ); ?>
<form
	class="variations_form cart"
	action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	method="post"
	enctype="multipart/form-data"
	data-product_id="<?php echo absint( $product->get_id() ); ?>"
	data-product_variations="<?php echo $variations_attr; // WPCS: XSS ok. ?>"
>
	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<pre><?php var_dump( $product_attributes );?></pre>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?></p>
	<?php else : ?>
		<?php foreach ( $attributes as $attribute_name => $options ) : ?>
			<?php
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );
				$default_option = $product->get_variation_default_attribute( $attribute_name );
				if ( isset( $_REQUEST[ $attribute_key ] ) ) {
					$default_option = wc_clean( wp_unslash( $_REQUEST[ $attribute_key ] ) );
				}
			?>
			<h2 class="trans__product__cart__title"><?php echo wc_attribute_label( $attribute_name ); // WPCS: XSS ok. ?></h3>
			<?php foreach ( $options as $option_name ): ?>
				<p>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $attribute_key ); ?>"
							value="<?php echo esc_attr( $option_name ); ?>"
							<?php checked( $default_option, $option_name ); ?>
						/>
						<?php echo esc_html( $option_name ); ?>
					</label>
				</p>
			<?php endforeach; ?>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );
