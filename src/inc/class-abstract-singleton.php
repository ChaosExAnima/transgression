<?php declare( strict_types=1 );

namespace Transgression;

use Exception;

abstract class Singleton {
	/** @var Singleton[] */
	private static array $instances = [];

	final public static function instance(): static {
		$class = get_called_class();
		if ( ! isset( static::$instances[ $class ] ) ) {
			static::$instances[$class] = new static();
		}
		return static::$instances[$class];
	}

	public function init() {}

	protected function __construct() {}

	private function __clone() {}

	public function __wakeup() {
		throw new \Exception( "Cannot unserialize singleton" );
	}
}
