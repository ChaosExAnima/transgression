<?php declare( strict_types=1 );

namespace Transgression\Modules;

abstract class Module {
	/** @var array<string, string> */
	const REQUIRED_PLUGINS = [];

	public function __construct() {
		if ( ! static::check_plugins() ) {
			return;
		}

		if ( is_callable( [ $this, 'init' ] ) ) {
			add_action( 'init', [ $this, 'init' ] );
		}
	}

	/**
	 * Checks if any plugin is not active
	 *
	 * @return boolean
	 */
	public static function check_plugins(): bool {
		foreach ( static::REQUIRED_PLUGINS as $plugin ) {
			if ( ! is_plugin_active( $plugin ) ) {
				return false;
			}
		}
		return true;
	}
}
