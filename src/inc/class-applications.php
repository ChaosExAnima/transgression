<?php declare( strict_types=1 );

namespace Transgression;

use WP_Post;

class Applications extends Singleton {
	const POST_TYPE = 'application';

	const LABELS = [
		'name' => 'Applications',
		'singular_name' => 'Application',
		'edit_item' => 'Review Application',
	];

	const FIELDS = [
		'post_title' => 'Name',
		'email' => 'Email',
		'pronouns' => 'Pronouns',
		'identity' => 'How they identify',
		'associates' => 'Who they know',
		'referrer' => 'How they know us',
		'warnings' => 'Potential conflicts',
		'accessibility' => 'Accessibility concerns',
		'extra' => 'Additional comments',
	];

	const MIME_TYPES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png' => 'image/png',
		'tiff|tif' => 'image/tiff',
		'webp' => 'image/webp',
	];

	public function init() {
		register_post_type( self::POST_TYPE, [
			'label' => self::LABELS['name'],
			'labels' => self::LABELS,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			'supports' => ['comments'],
			'register_meta_box_cb' => [$this, 'meta_boxes'],
			'delete_with_user' => true,
		] );

		add_action( 'edit_form_after_title', [$this, 'render_title'] );
		add_action( 'save_post_' . self::POST_TYPE, [$this, 'save'] );
	}

	public function save( int $post_id ) {
		$new_comment = sanitize_textarea_field( $_POST['newcomment'] ?? '' );
		$user = wp_get_current_user();
		if ( $new_comment ) {
			wp_insert_comment( [
				'comment_author' => $user->display_name,
				'comment_post_ID' => $post_id,
				'comment_content' => $new_comment,
			] );
		}
	}

	public function meta_boxes() {
		add_meta_box(
			self::POST_TYPE . '_fields',
			'Form Entry',
			[$this, 'render_metabox_fields'],
			self::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			self::POST_TYPE . '_comments',
			'Comments',
			[$this, 'render_metabox_comments'],
			self::POST_TYPE,
			'normal'
		);
		add_meta_box(
			self::POST_TYPE . '_verdict',
			'Verdict',
			[$this, 'render_metabox_verdict'],
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			self::POST_TYPE . '_photo',
			'Photo',
			[$this, 'render_metabox_photo'],
			self::POST_TYPE,
			'side',
			'high'
		);

		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'pageparentdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'commentstatusdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
	}

	public function render_title( WP_Post $post ) {
		if ( $post->post_type !== self::POST_TYPE ) {
			return;
		}
		printf(
			'<div id="titlediv"><div id="titlewrap"><h1>Applicant: %s</h1></div></div>',
			esc_html( get_the_title( $post ) )
		);
	}

	public function render_metabox_fields( WP_Post $post ) {
		echo '<table class="app-fields"><tbody>';
		foreach ( self::FIELDS as $key => $name ) {
			echo '<tr>';
			printf(
				'<td><strong>%s<strong></td>',
				esc_html( $name )
			);
			$value = $post->{$key};
			if ( $value ) {
				if ( $key === 'email' ) {
					printf( '<td><a href="mailto:%1$s" target="_blank">%1$s</a></td>', esc_attr( $value ) );
				} else {
					printf( '<td>%s</td>', esc_html( $value ) );
				}
			} else {
				echo '<td><em>Not provided</em></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function render_metabox_comments( WP_Post $post ) {
		/** @var \WP_Comment[] */
		$comments = get_comments( ['post_id' => $post->ID] );
		wp_list_comments( ['post_id' => $post->ID] );
		echo '<ul class="app-comments">';
		foreach ( $comments as $comment ) {
			printf(
				'<li><q>%1$s</q> - %2$s <time datetime="%4$s">%3$s ago</time></li>',
				esc_html( $comment->comment_content ),
				esc_html( $comment->comment_author ),
				esc_html( human_time_diff( strtotime( $comment->comment_date ) ) ),
				esc_attr( $comment->comment_date )
			);
		}
		echo '</ul>';
		echo '<p><textarea class="large-text app-newcomment" placeholder="Leave a comment" name="newcomment"></textarea></p>';
		submit_button();
		echo '<div class="clear"></div>';
	}

	public function render_metabox_verdict( WP_Post $post ) {
		
	}

	public function render_metabox_photo( WP_Post $post ) {
		$image_url = $post->photo_img ?: $post->photo_url;
		$social_url = $post->photo_url;

		['ext' => $ext] = wp_check_filetype( $image_url, self::MIME_TYPES );

		if ( $ext !== false ) {
			printf( '<img src="%s" width="100%%" class="app-photo" />', esc_url( $image_url ) );
		} else if ( $social_url ) {
			$social_name = 'Social Link';
			if ( str_contains( $social_url, 'facebook.com' ) ) {
				$social_name = 'Facebook';
			} else if ( str_contains( $social_url, 'instagram.com' ) ) {
				$social_name = 'Instagram';
			}
			printf(
				'<p class="app-social"><a href="%s" target="_blank">%s</a></p>',
				esc_url( $social_url ),
				esc_html( $social_name )
			);
		} else {
			echo '<p><strong>Nothing provided!</strong></p>';
		}
	}
}
