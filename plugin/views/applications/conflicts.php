<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var \WP_Query */
$query = $params['query'];

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<ul>
		<?php while ( $query->have_posts() ) :
			$query->the_post();
			?>
			<li>
				<?php edit_post_link( get_the_title(), '', ':' ); ?>&nbsp;
				<?php echo esc_html( get_post_meta( get_the_ID(), 'conflict', true ) ); ?>
			</li>
		<?php endwhile; ?>
	</ul>
</div>
