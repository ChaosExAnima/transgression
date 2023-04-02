<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Modules\Email\Emailer;
use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Blocks\Block_Helper;

class JetForms extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'jetformbuilder/jet-form-builder.php' ];

	public function __construct( protected Emailer $emailer ) {
		if ( ! self::check_plugins() ) {
			return;
		}

		add_filter( 'jet-form-builder/action/insert-post/pre-check', [ $this, 'before_insert' ] );
		add_action( 'jet-form-builder/action/after-post-insert', [ $this, 'after_post_insert' ], 10, 2 );
	}

	/**
	 * Stops the form entry and sends an email to the user if they already are registered
	 *
	 * @see \Jet_Form_Builder\Actions\Methods\Post_Modification\Base_Post_Action::pre_check()
	 * @param bool $return
	 * @return bool
	 */
	public function before_insert( bool $return ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_REQUEST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_REQUEST['email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$user_id = email_exists( $email );
			if ( $user_id ) {
				$email = $this->emailer->create();
				$email->with_template( 'app_dupe' )->to_user( $user_id )->send();
				return false;
			}
		}
		return $return;
	}

	/**
	 * Adds the form ID as meta to the newly created application
	 *
	 * @see \Jet_Form_Builder\Actions\Methods\Post_Modification\Base_Post_Action::pre_check()
	 * @param Base $action Current action
	 * @param Action_Handler $handler Current action handler
	 * @return void
	 */
	public function after_post_insert( Base $action, Action_Handler $handler ) {
		$form_id = $handler->get_form_id();
		$post_id = $handler->get_inserted_post_id( $action->_id );

		if ( $post_id ) {
			add_post_meta( $post_id, '_form_id', $form_id, true );
		}
	}

	/**
	 * Extracts html name attribute and form label as array for metabox display
	 *
	 * @param int $form_id
	 * @return array
	 */
	public function get_form_fields_for_meta( int $form_id ): array {
		$fields = [];
		if ( ! self::check_plugins() ) {
			return $fields;
		}

		$content = Block_Helper::get_blocks_by_post( $form_id );
		$blocks = Block_Helper::filter_blocks_by_namespace( $content );

		foreach ( $blocks as $block ) {
			$name = $block['attrs']['name'] ?? null;
			if (
				! $name ||
				str_contains( $name, 'photo' ) ||
				str_contains( $block['blockName'], 'checkbox' )
			) {
				continue;
			}
			if ( ! empty( $block['attrs']['label'] ) ) {
				$fields[ $block['attrs']['name'] ] = $block['attrs']['label'];
			}
		}
		return $fields;
	}
}
