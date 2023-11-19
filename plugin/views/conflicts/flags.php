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
<form action="" method="POST" class="add-flag">
	<span>Add new flag:</span>
	<input type="text" name="name" placeholder="Name" required data-1p-ignore />
	<input type="text" name="name" placeholder="Instagram" data-1p-ignore />
	<?php submit_button( 'Add flag', 'primary', 'submit', false ); ?>
</form>
