<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Jet_Form_Builder\Actions\Methods\{Abstract_Modifier, Base_Object_Property, Object_Dynamic_Property};
use Jet_Form_Builder\Exceptions\Action_Exception;
use Transgression\Person;

use function Transgression\prefix;

class Jetform_Application_Property extends Base_Object_Property implements Object_Dynamic_Property {
	public function get_id(): string {
		return prefix( 'app_email' );
	}

	public function get_label(): string {
		return 'Application Email';
	}

	/**
	 * @param string $key
	 * @param $value
	 * @param Abstract_Modifier $modifier
	 *
	 * @throws Action_Exception
	 */
	public function do_before( string $key, mixed $value, Abstract_Modifier $modifier ) {
		$email = $modifier->get_value( 'email' );
		if ( ! $email ) {
			throw new Action_Exception( 'no_email' );
		}
		$person = Person::from_email( $email );
		if ( ! $person ) {
			throw new Action_Exception( 'no_person' );
		}
		$this->value = $person->application->ID;
	}

	public function is_supported( string $key, mixed $value ): bool {
		return true;
	}
}
