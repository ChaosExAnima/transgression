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
			$nonce = nonce_array( "conflict-{$app->ID}" );
			$flag_url = $admin->get_url( [
				'app_id' => $app->ID,
				...$nonce,
			] );
			$resolve_url = $admin->get_action_url(
				'resolve',
				(string) $app->ID,
				$nonce
			);
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

