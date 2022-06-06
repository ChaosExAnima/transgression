<?php declare( strict_types=1 );

namespace Transgression;

use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use WP_Error;

function before_application_insert( bool $return, array $source ): bool {
	if ( isset( $source['meta_input']['email'] ) ) {
		$email = trim( (string) $source['meta_input']['email'] );
		$user_id = email_exists( $email );
		if ( $user_id ) {
			$result = Emails::instance()->send_user_email( $user_id, 'email_duplicate' );
			if ( is_wp_error( $result ) ) {
				log_error( $result );
			}
			return false;
		}
	}
	return $return;
}
add_filter( 'jet-form-builder/action/insert-post/pre-check', cb( 'before_application_insert' ), 10, 2 );

function after_application_insert( Base $action, Action_Handler $handler ) {
	$form_id = $handler->get_form_id();
	$post_id = $handler->get_inserted_post_id( $action->_id );

	if ( $post_id ) {
		add_post_meta( $post_id, '_form_id', $form_id, true );
		add_image_to_application( $post_id );
	}
}
add_action( 'jet-form-builder/action/after-post-insert', cb( 'after_application_insert' ), 10, 2 );

function add_image_to_application( int $post_id ) {
	$post = get_post( $post_id );
	$image_path = $post->photo_local_url;
	if ( !$image_path && $post->photo_url ) {
		$remote_url = $post->photo_url;
		$mime_types = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		];
		$remote_type = wp_check_filetype( $remote_url, $mime_types );
		if ( $remote_type !== false ) {
			$id = media_sideload_image( $remote_url, $post_id, 'Applicant photo', 'id' );
		}
	}
}
