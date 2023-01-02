<?php declare( strict_types=1 );

namespace Transgression;

/** @var array<string, string> */
$fields = $params['fields'];
/** @var \WP_Post */
$post = $params['post'];
?>
<table class="app-fields">
	<tbody>
		<?php foreach ( $fields as $key => $name ) : ?>
			<tr id="<?php echo esc_attr( $key ); ?>">
				<th><?php echo esc_html( $name ); ?></th>
				<td>
					<?php $value = $post->{$key}; ?>
					<?php if ( ! $value ) : ?>
						<em>Not provided</em>
					<?php elseif ( 'email' === $key ) : ?>
						<a href="mailto:<?php echo esc_attr( $value ); ?>" target="_blank">
							<?php echo esc_attr( $value ); ?>
						</a>
					<?php else :
						$paragraphs = preg_split( '/\n\s*\n/', $value, -1, PREG_SPLIT_NO_EMPTY );
						foreach ( $paragraphs as $paragraph ) {
							echo esc_html( wptexturize( trim( $paragraph ) ) ) . "<br/>\n";
						}
						endif;
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
