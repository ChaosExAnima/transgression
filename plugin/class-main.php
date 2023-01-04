<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Admin\Page;
use Transgression\Modules\{Applications, Attendance, Discord, People, Email\Emailer, WooCommerce};

class Main {
	public function init() {
		$logger = new Logger();
		$emailer = new Emailer();

		$settings = new Page( 'transgression_settings' );
		$settings->as_subpage( 'options-general.php', 'ticketing', 'Ticketing Settings', 'Ticketing' );

		new Applications( $emailer, $logger );
		new Attendance( $logger );
		new Discord();
		new People( $emailer, $logger );
		new WooCommerce( $logger, $settings );
	}
}
