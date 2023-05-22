<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Admin\Page_Options;
use Transgression\Modules\{Applications, Attendance, Auth0, Discord, People, Email\Emailer, JetForms, WooCommerce};

class Main {
	public function init() {
		$logger = new Logger();
		$emailer = new Emailer();

		$settings = new Page_Options( 'settings', 'Ticketing Settings', 'Ticketing' );
		$settings->as_subpage( 'options-general.php' );

		$jetforms = new JetForms( $emailer );
		new Applications( $jetforms, $emailer );
		new Attendance();
		$people = new People( $emailer );
		new Auth0( $people, $settings );
		new Discord( $settings, $logger );
		new WooCommerce( $settings );
	}
}
