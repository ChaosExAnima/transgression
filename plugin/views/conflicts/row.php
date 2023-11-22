<?php declare( strict_types = 1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

use Transgression\Modules\Conflicts;

/** @var \WP_Post */
$app = $params['app'];
/** @var Admin\Page */
$admin = $params['admin'];

if ( $app->hide_conflict ) {
	return;
}
/** @var \WP_Comment[] */
$comments = get_comments( [
	'post_id' => $app->ID,
	'order' => 'ASC',
	'type' => Conflicts::COMMENT_TYPE,
] );
$flags = get_the_terms( $app, Conflicts::TAX_SLUG );
if ( ! is_array( $flags ) ) {
	$flags = [];
}
$nonce_action = "conflict-{$app->ID}";
$flag_url = wp_nonce_url( $admin->get_url( [ 'app_id' => $app->ID ] ), $nonce_action );
$resolve_url = wp_nonce_url( $admin->get_action_url( 'resolve', (string) $app->ID ), $nonce_action );

?>
<tr class="conflict">
	<td class="min actions">
		<button
			aria-controls="comments-<?php echo absint( $app->ID ); ?>"
			aria-pressed="false"
			class="button comment-action"
			data-action="comment"
		>
			<span class="dashicons dashicons-admin-comments"></span>
			<?php if ( count( $comments ) > 0 ) : ?>
			&nbsp;
			<?php echo esc_html( count( $comments ) ); ?>
			<?php endif; ?>
		</button>
		<button
			aria-controls="flags-<?php echo absint( $app->ID ); ?>"
			aria-pressed="false"
			class="button flag-action"
			data-action="flag"
		>
			<span class="dashicons dashicons-flag"></span>
			<?php if ( count( $flags ) > 0 ) : ?>
			&nbsp;
			<?php echo esc_html( count( $flags ) ); ?>
			<?php endif; ?>
		</button>
		<a
			class="button resolve-action"
			data-action="resolve"
			href="<?php echo esc_url( $resolve_url ); ?>"
			title="Mark this as resolved"
		><span class="dashicons dashicons-trash"></span>
		</a>
	</td>
	<td class="min">
		<?php edit_post_link( get_the_title() ); ?>
	</td>
	<td class="text">
		<?php echo wp_kses( linkify( $app->conflicts ), KSES_TAGS ); ?>
	</td>
</tr>
<tr class="flags" id="flags-<?php echo absint( $app->ID ); ?>" hidden>
	<td colspan="3">
	<?php
		load_view( 'conflicts/flags', [
			'id' => $app->ID,
			'admin' => $admin,
			'flags' => $flags,
		] );
		?>
	</td>
</tr>
<tr class="comments" id="comments-<?php echo absint( $app->ID ); ?>" hidden>
	<td colspan="3">
		<?php
		load_view( 'conflicts/comments', [
			'id' => $app->ID,
			'admin' => $admin,
			'comments' => $comments,
		] );
			?>
	</td>
</tr>
