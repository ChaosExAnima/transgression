<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Option;
use Transgression\Admin\Page_Options;

use function Transgression\load_view;

use const Transgression\PLUGIN_SLUG;

class ForbiddenTickets extends Module {
	public const USER_CODE_KEY = PLUGIN_SLUG . '_code';
	public const CACHE_ALL_KEY = 'all_codes';

	public function __construct() {
		$admin = new Page_Options( 'forbidden-tickets', 'Forbidden Tickets', 'Forbidden Tickets', [], 'manage_options' );
		$admin->add_render_callback( [ $this, 'render' ], 1 );
		$admin->as_page( 'ticket.svg', 56 );
		$admin->add_style( 'forbidden-tickets' );

		( new Option( 'landing_url', 'Landing URL' ) )
			->describe( 'The Forbidden Tickets landing page' )
			->of_type( 'url' )
			->on_page( $admin );
	}

	/**
	 * Render the admin page
	 *
	 * @return void
	 */
	public function render(): void {
		$codes = $this->get_all_codes();
		load_view( 'forbidden-tickets/admin', compact( 'codes' ) );
	}

	/**
	 * Generate a code
	 *
	 * @param bool $check_unique Check if the code is unique
	 * @return string
	 */
	public function generate_code( bool $check_unique = true ): string {
		$code = strtolower( wp_generate_password( 6, false ) );
		if ( ! $check_unique ) {
			return $code;
		}
		$codes = $this->get_all_codes();
		if ( in_array( $code, $codes, true ) ) {
			return $this->generate_code();
		}
		return $code;
	}

	/**
	 * Set the user code
	 *
	 * @param int $user_id User ID
	 * @param string $code Code
	 * @return void
	 */
	public function get_code( int $user_id ): string {
		$meta_value = get_user_meta( $user_id, self::USER_CODE_KEY, true );
		if ( $meta_value ) {
			return $meta_value;
		}

		$code = $this->generate_code();
		update_user_meta( $user_id, self::USER_CODE_KEY, $code );
		wp_cache_delete( self::CACHE_ALL_KEY, PLUGIN_SLUG );
		return $code;
	}

	/**
	 * Get all codes.
	 *
	 * @return array
	 */
	public function get_all_codes(): array {
		$cached = wp_cache_get( self::CACHE_ALL_KEY, PLUGIN_SLUG );
		if ( $cached ) {
			return $cached;
		}
		global $wpdb;
		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = %s", self::USER_CODE_KEY ) );
		wp_cache_set( self::CACHE_ALL_KEY, $codes, PLUGIN_SLUG );
		return $codes;
	}
}
