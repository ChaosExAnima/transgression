<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

use Transgression\Modules\Conflicts;

/** @var \WP_Query */
$query = $params['query'];
/** @var Admin\Page */
$admin = $params['admin'];

global $wpdb;
$raw_results = $wpdb->get_col(
	$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE count = 0 AND taxonomy = %s", Conflicts::TAX_SLUG )
);
$terms = get_terms( [
	'taxonomy' => Conflicts::TAX_SLUG,
	'include' => array_map( 'absint', $raw_results ),
	'hide_empty' => false,
] );
?>

<aside class="new-flag">
	<?php load_view( 'conflicts/new-flag', compact( 'admin' ) ); ?>
</aside>

<pre>
	<?php var_dump( $terms ); ?>
</pre>

<table class="wp-list-table widefat conflicts">
	<thead>
		<tr>
			<th class="min">Actions</th>
			<th class="min">Applicant</th>
			<th>Report</th>
		</tr>
	</thead>
	<tbody>
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			load_view( 'conflicts/row', [
				'app' => get_post(),
				'admin' => $admin,
			] );
		endwhile;
		?>
	</tbody>
	<tfoot>
		<tr>
			<th class="min"></th>
			<th class="min">Name</th>
			<th>Report</th>
		</tr>
	</tfoot>
</table>

