<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

use Transgression\Modules\Conflicts;

/** @var \WP_Query */
$query = $params['query'];
/** @var string */
$current_url = $params['current_url'];

?>

<table class="wp-list-table widefat conflicts">
	<thead>
		<tr>
			<th class="min">Actions</th>
			<th class="min">Applicant</th>
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
			/** @var \WP_Comment[] */
			$comments = get_comments( [
				'post_id' => $app->ID,
				'order' => 'ASC',
				'type' => Conflicts::COMMENT_TYPE,
			] );
			?>
			<tr>
				<td class="min actions">
					<button
						aria-controls="comments-<?php echo absint( $app->ID ); ?>"
						aria-pressed="false"
						class="button <?php echo count( $comments ) > 0 ? 'button-primary' : ''; ?> comment-action"
					>
						<span class="dashicons dashicons-admin-comments"></span>
						<?php if ( count( $comments ) > 0 ) : ?>
						&nbsp;
						<?php echo esc_html( count( $comments ) ); ?>
						<?php endif; ?>
					</button>
					<button
						class="button resolve-action"
						data-url="<?php echo esc_url( $resolve_url ); ?>"
						title="Mark this as resolved"
					><span class="dashicons dashicons-trash"></span>
					</button>
				</td>
				<td class="min">
					<?php edit_post_link( get_the_title() ); ?>
				</td>
				<td>
					<?php echo wp_kses( linkify( $app->conflicts ), KSES_TAGS ); ?>
				</td>
			</tr>
			<tr class="comments" id="comments-<?php echo absint( $app->ID ); ?>" hidden>
				<td colspan="3">
					<?php
					load_view( 'conflicts/comments', [
						'id' => $app->ID,
						'comments' => $comments,
						'url' => $current_url,
					] );
					?>
				</td>
			</tr>
		<?php endwhile; ?>
	</tbody>
	<tfoot>
		<tr>
			<th class="min"></th>
			<th class="min">Name</th>
			<th>Report</th>
		</tr>
	</tfoot>
</table>

<script type="module">
const actions = document.querySelectorAll('.actions > button');
for (const button of actions) {
	button.addEventListener('click', handleAction);
}

function handleAction(event) {
	event.preventDefault();
	if (this.classList.contains('resolve-action')) {
		if (this.dataset.url && confirm('Resolve this conflict?')) {
			location.assign(this.dataset.url);
		}
	} else if (this.classList.contains('comment-action')) {
		const commentsRow = document.getElementById(this.getAttribute('aria-controls'));
		if (commentsRow) {
			const show = !commentsRow.hidden;
			commentsRow.hidden = show;
			this.setAttribute('aria-pressed', !show);
		}
	}
}
</script>
