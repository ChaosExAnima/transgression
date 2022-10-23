<?php declare( strict_types=1 );

namespace Transgression;

/** @var \WP_Comment[] */
$comments = $params['comments'];
?>
<ul class="app-comments">
<?php foreach ( $comments as $comment ) : ?>
	<li>
		<q><?php echo esc_html( $comment->comment_author ); ?></q> -
		<?php echo esc_html( $comment->comment_author ); ?>,
		<?php render_time( $comment->comment_date_gmt, true ); ?>
	</li>
<?php endforeach; ?>
</ul>
<hr />
<p><textarea class="large-text app-newcomment" placeholder="Leave a comment" name="newcomment"></textarea></p>
<?php submit_button( 'Submit comment' ); ?>
<div class="clear"></div>
