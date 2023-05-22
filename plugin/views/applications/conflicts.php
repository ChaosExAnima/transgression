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
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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
				?>
				<tr>
					<td class="min">
						<a
							href="<?php echo esc_url( add_query_arg( 'resolve', get_the_ID(), $current_url ) ); ?>"
							title="Mark this as resolved"
						>x</a>
					</td>
					<td class="min">
						<?php edit_post_link( get_the_title() ); ?>
					</td>
					<td>
						<?php echo esc_html( get_post_meta( get_the_ID(), 'warnings', true ) ); ?>
					</td>
				</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
</div>
