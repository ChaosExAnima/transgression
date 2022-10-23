<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Admin\Option;

class Main {
	public function init() {

	}

	/**
	 * Registers a module and returns
	 *
	 * @param string $name
	 * @return boolean
	 */
	protected function register_module( string $name ): bool {
		$key = PLUGIN_SLUG . '_module_' . sanitize_title( $name );
		$option = new Option( $key, $name, true );
		$option->of_type( 'toggle' );

		return !! $option->get();
	}
}
