<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Admin\Page;
use Transgression\Modules\{Applications, Attendance, Auth0, Discord, People, Email\Emailer, JetForms, WooCommerce};

class Main {
	public function init() {
		$logger = new Logger();
		$emailer = new Emailer( $logger );

		$settings = new Page( 'transgression_settings' );
		$settings->as_subpage( 'options-general.php', 'ticketing', 'Ticketing Settings', 'Ticketing' );

		$jetforms = new JetForms( $emailer );
		new Applications( $jetforms, $emailer, $logger );
		new Attendance( $logger );
		$people = new People( $emailer, $logger );
		new Auth0( $people, $settings, $logger );
		new Discord( $settings, $logger );
		new WooCommerce( $logger, $settings );
	}
}
