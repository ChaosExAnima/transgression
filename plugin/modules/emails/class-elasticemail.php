<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option;

use function Transgression\prefix;

class ElasticEmail extends Email {
	/**
	 * @inheritDoc
	 */
	protected function attempt_send(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public static function init( Emailer $emailer ): void {
		$emailer->admin->add_setting(
			new Option( prefix( 'elasticemail_key' ), __( 'ElasticEmail Key', 'transgression' ) )
		);
	}

	/**
	 * @inheritDoc
	 */
	public static function template_option( string $key, string $name ): Option {
		return ( new Option( $key, $name ) );
	}
}
