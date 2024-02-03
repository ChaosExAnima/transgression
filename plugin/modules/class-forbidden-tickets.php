<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Page;

use const Transgression\PLUGIN_ROOT;

class ForbiddenTickets extends Module {
	public function __construct() {
		$admin = new Page( 'forbidden-tickets', 'Forbidden Tickets', 'Forbidden Tickets', 'manage_options' );
		$admin->add_render_callback( [ $this, 'render' ] );
		$admin->as_page( 'ticket.svg', 56 );
	}

	public function render(): void {
	}
}
