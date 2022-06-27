<?php declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$form_url = apply_filters(
	'woocommerce_add_to_cart_form_action',
	$product->add_to_cart_url(),
);
$button_text = is_user_logged_in()
	? 'Buy Tickets'
	: 'Log In To Buy';

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

use Transgression\WooCommerce;

do_action( 'woocommerce_before_single_product_summary' );

if ( WooCommerce::add_title_prefix( $product ) ) {
	echo '<h2 class="trans__product__subtitle">Transgression:</h2>';
}
the_title(
	'<h1 class="product_title trans__product__title">',
	'</h1>'
);
?>
<div class="trans__product__wrapper">
	<div class="trans__product__description">
		<?php the_content(); ?>
	</div>
	<form
		class="trans__product__cart"
		action="<?php echo esc_url( $form_url ); ?>"
		method="post"
		enctype="multipart/form-data"
	>
		<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>
		<?php if ( $product->is_type( 'variable' ) ): ?>
			<?php $default_variation = $product->get_variation_default_attribute( 'tier' ); ?>
			<fieldset>
				<legend class="trans__product__cart__title">
					<?php echo esc_html( wc_attribute_label( 'tier', $product ) ); ?>
				</legend>
				<?php foreach ( $product->get_available_variations() as $variation ): ?>
					<?php
						$variation_name = $variation['attributes']['attribute_tier'];
					?>
						<label>
							<input
								name="variation_id"
								type="radio"
								value="<?php echo esc_attr( $variation['variation_id'] ); ?>"
								<?php checked( $default_variation, $variation_name ); ?>
							/>
							<?php echo esc_html( $variation_name ); ?>
							<?php echo $variation['price_html']; ?>
						</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>
		<?php echo wc_get_stock_html( $product ); ?>

		<?php if ( is_user_logged_in() ): ?>
			<input type="hidden" name="add-to-cart" value="<?php the_ID(); ?>" />
		<?php else : ?>
			<p><input type="email" name="login-email" placeholder="Email" required /></p>
		<?php endif; ?>
		<button
			type="submit"
			class="trans__product__submit"
			<?php disabled( ! $product->is_in_stock() ) ?>
		>
			<?php echo esc_html( $button_text ); ?>
		</button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>
</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
