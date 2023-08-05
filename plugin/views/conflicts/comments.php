<?php declare( strict_types=1 );

/**
 * Template for showing conflicts page
 * @var array $params
 */

namespace Transgression;

/** @var int */
$app_id = $params['id'];
/** @var \WP_Comment[] */
$comments = $params['comments'];
/** @var Admin\Page */
$admin = $params['admin'];

$comment_url = $admin->get_url( [
	'app_id' => $app_id,
	...nonce_array( "conflict-{$app_id}" ),
] );

?>
<?php if ( count( $comments ) > 0 ) : ?>
	<ul>
		<?php foreach ( $comments as $comment ) : ?>
			<li id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>">
				<?php echo esc_html( $comment->comment_author ); ?>:
				<q><?php render_lines( $comment->comment_content ); ?></q>,
				<?php render_time( $comment->comment_date_gmt ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<hr />
<?php endif; ?>
<form action="<?php echo esc_url( $comment_url ); ?>" method="POST">
	<textarea class="large-text" placeholder="Leave a comment" name="comment"></textarea>
	<?php submit_button( 'Leave comment' ); ?>
</form>
