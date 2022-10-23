<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Modules\Email\Emailer;
use Transgression\Modules\People;

class Main {
	public function init() {
		$logger = new Logger();
		$emailer = new Emailer();
		$people = new People( $emailer, $logger );
	}
}
