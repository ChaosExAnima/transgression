<?php declare( strict_types=1 );

namespace Transgression;

$photo_url = $params['photo_url'];
$social_label = $params['social_label'];
$social_url = $params['social_url'];
$input_label = $params['input_label'];
$mime_types = $params['mime_types'];
?>

<?php if ( $photo_url ) : ?>
	<a href="<?php echo esc_url( $photo_url ); ?>" target="_blank">
		<img src="<?php echo esc_url( $photo_url ); ?>" width="100%" class="app-photo" />
	</a>
<?php endif; ?>

<?php if ( $social_url ) : ?>
	<p class="app-social">
		<?php if ( is_url( $social_url ) ) : ?>
			<a href="<?php echo esc_url( $social_url ); ?>" target="_blank">
				<?php echo esc_html( $social_label ); ?>
			</a>
		<?php else : ?>
			<?php echo esc_html( $social_url ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>

<p>
	<label>
		<?php echo esc_html( $input_label ); ?>:
		<input type="file" name="social_image" accept="<?php echo esc_attr( $mime_types ); ?>" />
	</label>
</p>
<?php submit_button( 'Save image' ); ?>
