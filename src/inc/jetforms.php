<?php declare( strict_types=1 );

namespace Transgression;

use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;

function after_application_insert( Base $action, Action_Handler $handler ) {
	$form_id = $handler->get_form_id();
	$post_id = $handler->get_inserted_post_id( $action->_id );

	if ( $post_id ) {
		add_post_meta( $post_id, '_form_id', $form_id, true );
	}
}
add_action( 'jet-form-builder/action/after-post-insert', cb( 'after_application_insert' ), 10, 2 );
