<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var int */
$app_id = $params['id'];
/** @var Admin\Page */
$admin = $params['admin'];
/** @var \WP_Term[] */
$flags = $params['flags'];
?>
<?php if ( count( $flags ) > 0 ) : ?>
	<ul>
		<?php foreach ( $flags as $flag ) : ?>
			<li id="flag-<?php echo esc_attr( $flag->term_id ); ?>">
				<?php echo esc_html( $flag->name ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<hr />
<?php endif; ?>
<?php
load_view( 'conflicts/new-flag', $params );
