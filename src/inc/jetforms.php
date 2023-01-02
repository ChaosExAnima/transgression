<?php declare( strict_types=1 );

namespace TransgressionTheme;

use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Blocks\Block_Helper;

if ( ! defined( 'JET_FORM_BUILDER_VERSION' ) || version_compare( JET_FORM_BUILDER_VERSION, '2.0.6', '<' ) ) {
	return;
}

function before_application_insert( bool $return ): bool {
	if ( isset( $_REQUEST['email'] ) ) {
		$email = trim( (string) $_REQUEST['email'] );
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
add_filter( 'jet-form-builder/action/insert-post/pre-check', cb( 'before_application_insert' ) );

function after_application_insert( Base $action, Action_Handler $handler ) {
	$form_id = $handler->get_form_id();
	$post_id = $handler->get_inserted_post_id( $action->_id );

	if ( $post_id ) {
		add_post_meta( $post_id, '_form_id', $form_id, true );
	}
}
add_action( 'jet-form-builder/action/after-post-insert', cb( 'after_application_insert' ), 10, 2 );

function get_form_fields_for_meta( int $form_id ): array {
	$content = Block_Helper::get_blocks_by_post( $form_id );
	$blocks = Block_Helper::filter_blocks_by_namespace( $content );

	$fields = [];
	foreach ( $blocks as $block ) {
		if ( !empty( $block['attrs']['label'] ) ) {
			$fields[ $block['attrs']['name'] ] = $block['attrs']['label'];
		}
	}
	return $fields;
}
