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
<form>
	<ul>
		<?php foreach ( $conflicts as $conflict ) : ?>
			<li>
				<?php echo esc_html( $conflict->name ); ?>
				<label for="checked-<?php echo absint( $conflict->term_id ); ?>">Checked</label>
				<input
					type="checkbox"
					name="checked"
					value="<?php echo absint( $conflict->term_id ); ?>"
					id="checked-<?php echo absint( $conflict->term_id ); ?>"
					<?php checked( in_array( $conflict->term_id, $checked_ids, true ) ); ?>
				/>
			</li>
		<?php endforeach; ?>
	</ul>
</form>
