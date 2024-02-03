<?php declare( strict_types=1 );

/**
 * Template for conflicts metabox
 * @var array $params
 */

namespace Transgression;

/** @var \WP_Term[] */
$conflicts = $params['conflicts'];
/** @var int[] */
$checked_ids = $params['checked'];

?>
<table class="app-conflicts wp-list-table widefat">
	<thead>
		<tr>
			<th>Name match</th>
			<th>Email</th>
			<th>Source</th>
			<th>Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $conflicts as $conflict ) : ?>
		<tr>
			<td><?php echo esc_html( $conflict->name ); ?></td>
			<td><?php echo esc_html( $conflict->email ?? 'None' ); ?></td>
			<td>
				<?php
				$author = $conflict->author ? get_user_by( 'id', $conflict->author ) : null;
				if ( $conflict->source ) {
					edit_post_link( get_the_title( $conflict->source ), '', '', $conflict->source );
				} elseif ( $author ) {
					echo esc_html( $author->user_nicename );
				}
				?>
			</td>
			<td>
				<a class="button">Delete</a>
				<a class="button button-warning">Confirm</a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
