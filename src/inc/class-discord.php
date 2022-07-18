<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Helpers\{Admin, Admin_Option};

class Discord extends Helpers\Singleton {
	protected Admin $admin;
	protected function __construct() {
		$admin = new Admin( 'discord' );
		$admin->as_subpage(
			'edit.php?post_type=' . Applications::POST_TYPE,
			'discord',
			'Discord'
		);

		( new Admin_Option( 'application_discord_hook', 'Application Webhook' ) )
			->of_type( 'url' )
			->on_page( $admin );
		( new Admin_Option( 'woo_discord_hook', 'Capitalism Webhook' ) )
			->of_type( 'url' )
			->on_page( $admin );
	}
}
