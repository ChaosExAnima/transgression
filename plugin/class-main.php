<?php declare( strict_types=1 );

namespace Transgression;

use Transgression\Admin\Page_Options;
use Transgression\Modules\{Applications, Attendance, Auth0, Conflicts, Discord, People, Email\Emailer, ForbiddenTickets, JetForms, WooCommerce};

use const Transgression\PLUGIN_VERSION;

class Main {
	protected const BLOCKS = [ 'tickets' ];

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_filter( 'script_loader_tag', [ $this, 'filter_script_module' ], 10, 3 );
	}

	/**
	 * Main initialization
	 * @return void
	 */
	public function init() {
		$logger = new Logger();
		$ft = new ForbiddenTickets();
		$emailer = new Emailer( $ft );

		$settings = new Page_Options( 'settings', 'Ticketing Settings', 'Ticketing' );
		$settings->as_subpage( 'options-general.php' );

		$jetforms = new JetForms( $emailer );
		new Applications( $jetforms, $emailer );
		new Attendance();
		$people = new People( $emailer );
		new Auth0( $people, $settings );
		new Conflicts();
		new Discord( $settings, $logger );
		new WooCommerce( $settings );

		Event_Schema::init();
	}

	/**
	 * Filters script tag to be modules
	 *
	 * @param string $tag HTML
	 * @param string $handle Script handle
	 * @param string $src Script url
	 * @return string
	 */
	public function filter_script_module( string $tag, string $handle, string $src ): string {
		if ( str_starts_with( $handle, PLUGIN_SLUG ) ) {
			return sprintf(
				// phpcs:ignore
				'<script type="module" id="%1$s" src="%2$s"></script>',
				esc_attr( $handle ),
				esc_url( $src )
			);
		}
		return $tag;
	}
}
