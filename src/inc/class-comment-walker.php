<?php declare( strict_types = 1 );

namespace Transgression;

use Walker_Comment;
use WP_Comment;

class Comment_Walker extends Walker_Comment {
	/**
	 * Outputs a comment
	 *
	 * @param WP_Comment $comment Comment to display.
	 * @param int        $depth   Depth of the current comment.
	 * @param array      $args    An array of arguments.
	 */
	// phpcs:ignore NeutronStandard.Functions.TypeHint.NoArgumentType
	protected function html5_comment( $comment, $depth, $args ) {
		printf(
			'<li id="comment-%1$s" class="%2$s"><article id="div-comment-%1$s" class="comment-body">',
			esc_attr( get_comment_ID() ),
			esc_attr( comment_class( $this->has_children ? 'parent' : '', $comment, null, false ) )
		);

		printf(
			'<div class="comment-author vcard">%s %s:</div>',
			esc_html( get_comment_author( $comment ) ),
			esc_html( $depth === 1 ? 'says' : 'replied' )
		);

		printf(
			'<div class="comment-content">%s</div>',
			get_comment_text( $comment->comment_ID )
		);

		echo '<footer class="comment-meta">';
		printf(
			'<span class="comment-date"><a href="%s"><time datetime="%s">%s ago</time></a></span>',
			esc_url( get_comment_link( $comment, $args ) ),
			get_comment_time( 'c' ),
			human_time_diff( get_comment_time( 'U', true ) )
		);

		if ( '1' == $comment->comment_approved ) {
			comment_reply_link( [
				'depth' => $depth,
				'max_depth' => $args['max_depth'],
			] );
		}

		edit_comment_link( 'Edit', '<span class="edit-link">', '</span>' );

		if ( '0' == $comment->comment_approved ) {
			echo '<em class="comment-awaiting-moderation">' .
				'Your comment is awaiting moderation. ' .
				'This is a preview; your comment will be visible after it has been approved.' .
				'</em>';
		}
		echo '</footer>';

		echo '</article>';
	}
}
