<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var \WP_Query */
$query = $params['query'];
/** @var string */
$current_url = $params['current_url'];

?>
<style>
	tr, td {
		width: 100%;
	}
	.min {
		white-space: nowrap;
		width: min-content;
	}
</style>

<table class="wp-list-table widefat striped">
	<thead>
		<tr>
			<th class="min"></th>
			<th class="min">Name</th>
			<th>Report</th>
		</tr>
	</thead>
	<tbody>
		<?php while ( $query->have_posts() ) :
			$query->the_post();
			$app = get_post();
			if ( $app->hide_conflict ) {
				continue;
			}
			$resolve_url = wp_nonce_url( add_query_arg( 'resolve', $app->ID, $current_url ), "conflict-{$app->ID}" );
			?>
			<tr>
				<td class="min">
					<a
						href="<?php echo esc_url( $resolve_url ); ?>"
						title="Mark this as resolved"
					>x</a>
				</td>
				<td class="min">
					<?php edit_post_link( get_the_title() ); ?>
				</td>
				<td>
					<?php echo esc_html( $app->warnings ); ?>
				</td>
			</tr>
		<?php endwhile; ?>
	</tbody>
</table>
