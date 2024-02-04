<?php declare( strict_types=1 );
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' ); ?>

<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_display_name">
			<?php esc_html_e( 'Name', 'transgression' ); ?>&nbsp;<span class="required">*</span>
		</label>
		<input
			type="text"
			class="woocommerce-Input woocommerce-Input--text input-text"
			name="account_display_name"
			id="account_display_name"
			value="<?php echo esc_attr( $user->display_name ); ?>"
		/>
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email">
			<?php esc_html_e( 'Email address', 'transgression' ); ?>&nbsp;<span class="required">*</span>
		</label>
		<input
			type="email"
			class="woocommerce-Input woocommerce-Input--email input-text"
			name="account_email"
			id="account_email"
			autocomplete="email"
			value="<?php echo esc_attr( $user->user_email ); ?>"
		/>
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_pronouns">
			<?php esc_html_e( 'Pronouns', 'transgression' ); ?>
		</label>
		<input
			type="text"
			class="woocommerce-Input woocommerce-Input--pronouns input-text"
			name="account_pronouns"
			id="account_pronouns"
			value="<?php echo esc_attr( $user->pronouns ); ?>"
		/>
	</p>

	<?php do_action( 'woocommerce_edit_account_form' ); ?>

	<p>
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button
			type="submit"
			class="woocommerce-Button button"
			name="save_account_details"
			value="<?php esc_attr_e( 'Save changes', 'transgression' ); ?>"
		>
			<?php esc_html_e( 'Save changes', 'transgression' ); ?>
		</button>
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
