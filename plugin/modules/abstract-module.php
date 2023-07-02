<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;

abstract class Module {
	/** @var array<string, string> */
	const REQUIRED_PLUGINS = [];

	/** @var Page|null Page admin, if any */
	protected ?Page $admin = null;

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
