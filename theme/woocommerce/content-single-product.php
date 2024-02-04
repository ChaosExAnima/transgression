<?php declare( strict_types=1 );
/**
 * The template for displaying product content in the single-product.php template
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

namespace TransgressionTheme;

use function Transgression\get_current_url;
use function Transgression\strip_query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WC_Product $product */
global $product;

$title_parts = explode( ':', $product->get_name(), 2 );
$the_title = trim( count( $title_parts ) > 1 ? $title_parts[1] : $title_parts[0] );

$form_url = apply_filters(
	'woocommerce_add_to_cart_form_action',
	$product->add_to_cart_url(),
);
$button_text = is_user_logged_in()
	? 'Buy Tickets'
	: 'Log In To Buy';

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
?>

<?php if ( count( $title_parts ) > 1 ) : ?>
	<h2 class="trans__product__subtitle"><?php echo esc_html( trim( $title_parts[0] ) ); ?>:</h2>
<?php endif; ?>
<h1 class="product_title trans__product__title">
	<?php echo esc_html( $the_title ); ?>
</h1>
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
		<?php if ( $product instanceof \WC_Product_Variable ) : ?>
			<?php $default_variation = $product->get_variation_default_attribute( 'tier' ); ?>
			<fieldset>
				<legend class="trans__product__cart__title">
					<?php echo esc_html( wc_attribute_label( 'tier', $product ) ); ?>
				</legend>
				<?php foreach ( $product->get_available_variations() as $variation ) : ?>
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
							<?php echo $variation['price_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>
		<?php echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( is_user_logged_in() ) : ?>
			<input type="hidden" name="add-to-cart" value="<?php the_ID(); ?>" />
		<?php else : ?>
			<p>
				<input
					type="email"
					name="login-email"
					placeholder="Email"
					required
					<?php disabled( ! $product->is_in_stock() ); ?>
				/>
			</p>
			<?php if ( apply_filters( 'transgression_social_configured', false ) ) : ?>
				<p class="trans__login__oauth">
					Log in with: <?php do_action( 'transgression_social_login', $product->is_in_stock() ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<button
			type="submit"
			class="trans__product__submit"
			<?php disabled( ! $product->is_in_stock() ); ?>
		>
			<?php echo esc_html( $button_text ); ?>
		</button>

		<?php if ( is_user_logged_in() ) : ?>
			<p><a
				href="<?php echo esc_url( wp_logout_url( strip_query( get_current_url() ) ) ); ?>"
				class="logout"
			>Log out</a></p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>
</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
