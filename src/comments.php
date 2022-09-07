<?php declare( strict_types = 1 );

use Transgression\Comment_Walker;

if ( post_password_required() ) {
	return;
}

$query_args = [
	'style' => 'ol',
	'walker' => new Comment_Walker(),
];

$user = wp_get_current_user();
$replying_to_id = isset( $_GET['replytocom'] ) ? absint( $_GET['replytocom'] ) : 0;

?>
<aside class="comments">
	<h2>Cruising Board</h2>
	<?php if ( is_user_logged_in() ) : ?>
		<?php if ( have_comments() ) : ?>
			<ol class="comments__list">
				<?php wp_list_comments( $query_args ); ?>
			</ol>
		<?php endif; ?>

		<form
			novalidate
			method="post"
			id="respond"
			class="comment__form"
			action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ) ?>"
		>
			<p class="comment__user">
				<?php
				if ( $replying_to_id ) {
					printf(
						'Replying to <a href="%s">%s</a>',
						esc_url( get_comment_link( $replying_to_id ) ),
						esc_html( get_comment_author( $replying_to_id ) ),
					);
				} else {
					echo 'Commenting';
				}
				printf(
					' as <a href="%s">%s</a>.',
					esc_url( wc_get_page_permalink( 'myaccount' ) ),
					esc_html( $user->display_name )
				);
				if ( $replying_to_id ) {
					printf(
						' <a href="%s#respond">Stop replying</a>.',
						esc_url( get_permalink() )
					);
				}
				?>
			</p>
			<p><textarea id="comment" name="comment" cols="45" rows="4" maxlength="65525"></textarea></p>
			<p class="comments__author">
				<label for="author">Name</label>
				<input
					id="author"
					maxlength="245"
					name="author"
					placeholder="Anon"
					required
					size="30"
					type="text"
					value="<?php echo esc_attr( $user->display_name ); ?>"
				>
			</p>
			<p>
				<button type="submit" id="submit" class="submit">
					<?php comment_form_title( 'Post something', 'Reply to %s', false ); ?>
				</button>
				<?php comment_id_fields(); ?>
			</p>
		</form>
	<?php else : ?>
		<p>You must be logged in to see cruising posts!</p>
		<form method="POST" action="" class="comments__login">
			<p><input type="email" name="login-email" placeholder="Email" required /></p>
			<button type="submit">Send login email</button>
		</form>
	<?php endif; ?>
</aside>
