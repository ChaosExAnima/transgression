<?php declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

<?php
/**
 * Hook: woocommerce_before_single_product_summary.
 *
 * @hooked woocommerce_show_product_sale_flash - 10
 * @hooked woocommerce_show_product_images - 20
 */
do_action( 'woocommerce_before_single_product_summary' );

the_title( '<h1 class="product_title trans__product__title">', '</h1>' );
?>
<div class="trans__product__wrapper">
	<div class="trans__product__description">
		<?php the_content(); ?>
	</div>
	<form
		class="trans__product__cart"
		action="<?php echo esc_url( $product->get_permalink() ); ?>"
		method="post"
		enctype="multipart/form-data"
	>
		<?php if ( $product->is_type( 'variable' ) ): ?>
			<?php $default_variation = $product->get_variation_default_attribute( 'tier' ); ?>
			<h2 class="trans__product__cart__title"><?php echo wc_attribute_label( 'tier', $product ); // WPCS: XSS ok. ?></h3>
			<?php foreach ( $product->get_available_variations() as $variation ): ?>
				<?php
					$variation_name = $variation['attributes']['attribute_tier'];
				?><p>
					<input
						id="variation-<?php echo absint( $variation['variation_id'] ); ?>"
						name="tier"
						type="radio"
						value="<?php echo esc_attr( $variation_name ); ?>"
						<?php checked( $default_variation, $variation_name ); ?>
					/>
					<label for="variation-<?php echo absint( $variation['variation_id'] ); ?>">
						<?php echo esc_html( $variation_name ); ?>
						<?php echo $variation['price_html']; ?>
					</label>
				</p>
			<?php endforeach; ?>
		<?php endif; ?>
	</form>
</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
