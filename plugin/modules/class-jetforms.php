<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Modules\Email\Emailer;
use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Blocks\Block_Helper;
use Jet_Form_Builder\Classes\Arrayable\Collection;
use Transgression\Person;

use function Transgression\prefix;

class JetForms extends Module {
	/** @inheritDoc */
	const REQUIRED_PLUGINS = [ 'jetformbuilder/jet-form-builder.php' ];

	public function __construct( protected Emailer $emailer ) {
		if ( ! self::check_plugins() ) {
			return;
		}

		add_filter( 'jet-form-builder/editor/hidden-field/config', [ $this, 'add_app_id_to_hidden' ] );
		add_filter( 'jet-form-builder/fields/hidden-field/value-cb', [ $this, 'insert_email_app_id' ], 10, 2 );
		add_filter( 'jet-form-builder/post-modifier/object-properties', [ $this, 'check_emails' ] );
		add_filter( 'jet-form-builder/action/insert-post/pre-check', [ $this, 'before_insert' ] );
		add_action( 'jet-form-builder/action/after-post-insert', [ $this, 'after_post_insert' ], 10, 2 );
		add_filter( 'jet-form-builder/post-type/args', [ $this, 'filter_post_type_args' ] );
	}

	public function add_app_id_to_hidden( array $block_data ): array {
		if ( isset( $block_data['sources'] ) ) {
			$block_data['sources'][] = [
				'value' => prefix( 'app_id' ),
				'label' => 'App Email ID',
			];
		}
		return $block_data;
	}

	public function insert_email_app_id( mixed $callback, string $value ): mixed {
		if ( prefix( 'app_id' ) === $value ) {
			return [ $this, 'insert_email' ];
		}
		return $callback;
	}

	public function insert_email(): ?int {
		var_dump( $_REQUEST );
		die;
		return null;
	}

	public function check_emails( Collection $collection ): Collection {
		require_once __DIR__ . '/inc/class-jetforms-app-property.php';
		$collection->add( new Jetform_Application_Property() );
		return $collection;
	}

	/**
	 * Stops the form entry and sends an email to the user if they already are registered
	 *
	 * @see \Jet_Form_Builder\Actions\Methods\Post_Modification\Base_Post_Action::pre_check()
	 * @param bool $return_value
	 * @return bool
	 */
	public function before_insert( bool $return_value ): bool {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_REQUEST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_REQUEST['email'] ) );
			$person = Person::from_email( $email );
			if ( $person->user_id ) {
				$email = $this->emailer->create();
				$email->with_template( 'app_dupe' )->to_user( $person->user_id )->send();
				return false;
			}
		}
		// phpcs:enable
		return $return_value;
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
	 * Updates an application meta from field data
	 *
	 * @param array $input Form input
	 * @param \WP_Post $application Application to update
	 * @return void
	 */
	public function update_application( array $input, \WP_Post $application ) {
		$form_id = $application->_form_id;
		if ( ! $form_id ) {
			return;
		}
		$fields = $this->get_form_fields_for_meta( $form_id ); // This won't work!
		foreach ( array_keys( $fields ) as $field ) {
			$value = sanitize_text_field( $input[ $field ] ?? '' );
			if ( $value && $application->{$field} !== $value ) {
				update_post_meta( $application->ID, $field, $value );
			}
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

	/**
	 * Adjusts Jetform post type args to have higher perms
	 *
	 * @param array $args
	 * @return array
	 */
	public function filter_post_type_args( array $args ): array {
		$args['public'] = false;
		$args['show_in_admin_bar'] = false;
		$args['capability_type'] = 'page';
		return $args;
	}
}
