<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Modules\{Applications, People, Email\Emailer};

class Main {
	public function init() {
		$logger = new Logger();
		$emailer = new Emailer();
		$people = new People( $emailer, $logger );
		$apps = new Applications( $emailer, $logger );
	}
}
