<?php declare( strict_types=1 );
// @phpcs:disable WordPress.Security.NonceVerification

namespace Transgression\Modules;

use Transgression\Admin\Option;
use Transgression\Admin\Page_Options;

use function Transgression\load_view;

use const Transgression\PLUGIN_SLUG;

class ForbiddenTickets extends Module {
	public const USER_CODE_KEY = PLUGIN_SLUG . '_code';
	public const OPTION_UNUSED_CODES = PLUGIN_SLUG . '_unused_codes';
	public const CACHE_ALL_KEY = 'all_codes';

	public const UNUSED_COUNT = 100;

	protected Page_Options $admin;

	public function __construct() {
		$admin = new Page_Options( 'forbidden_tickets', 'Forbidden Tickets', 'Forbidden Tickets', [], 'manage_options' );
		$this->admin = $admin;
		$admin->add_action( 'generate_codes', [ $this, 'action_generate_codes' ] );
		$admin->as_page( 'ticket.svg', 56 );
		$admin->add_style( 'forbidden-tickets' );

		$admin->add_settings(
			( new Option( 'copy_codes', 'Event codes' ) )
				->of_type( 'none' )
				->render_after( [ $this, 'render_copy' ] ),
			( new Option( 'remaining_codes', 'Remaining codes' ) )
				->of_type( 'none' )
				->render_after( [ $this, 'render_remaining' ] ),
			( new Option( 'producer_slug', 'Landing URL' ) )
				->describe( 'The Forbidden Tickets producer slug' )
				->on_page( $admin ),
		);

		$admin->register_message(
			'codes_generated',
			sprintf(
				'Generated another %d codes. Copy and paste in <a href="%s">an event</a>.',
				absint( $_GET['count'] ?? '' ),
				$this->event_url( '/panel/pages/events+%s?tab=events' )
			),
			'success'
		);
	}

	/**
	 * Render the admin page
	 *
	 * @return void
	 */
	public function render(): void {
		load_view( 'forbidden-tickets/admin', [
			'codes' => $this->all_codes(),
			'unused_count' => count( $this->unused_codes() ),
		] );
	}

	/**
	 * Render the remaining codes
	 *
	 * @return void
	 */
	public function render_remaining() {
		$unused_count = count( $this->unused_codes() );
		$disabled = $unused_count === self::UNUSED_COUNT;
		printf(
			'<div class="flex-row"><span>%d</span>',
			absint( $unused_count )
		);
		if ( ! $disabled || true ) {
			$regen_url = $this->admin->get_url( [
				'generate_codes' => '1',
				'_wpnonce' => wp_create_nonce( 'regenerate_codes' ),
			] );
			printf(
				'<a class="button button-secondary" href="%s">Regenerate</a>',
				esc_url( $regen_url ),
			);
		} else {
			echo '<a class="button button-disabled">Regenerate</p>';
		}
		echo '</div>';
	}

	public function render_copy() {
		$all_codes = $this->all_codes();
		printf(
			'<div class="flex-row">
				<code id="codes">%s<span class="dashicons dashicons-editor-paste-text"></span></code>
				<p id="result" class="hidden copy-success">Successfully copied codes!</p>
			</div>',
			esc_textarea( implode( ',', $all_codes ) )
		);
	}

	/**
	 * Handle the generate codes button click
	 *
	 * @return void
	 */
	public function action_generate_codes() {
		$generated = $this->generate_codes();
		$this->admin->redirect_message( 'codes_generated', [ 'count' => $generated ] );
	}

	/**
	 * Generate codes
	 *
	 * @return int
	 */
	protected function generate_codes(): int {
		$unused_codes = $this->unused_codes();
		$needed = self::UNUSED_COUNT - count( $unused_codes );
		if ( $needed === 0 ) {
			return 0;
		}
		$all_codes = $this->all_codes();
		$count = $needed;
		while ( $needed > 0 ) {
			$code = strtolower( wp_generate_password( 6, false ) );
			if ( ! in_array( $code, $all_codes, true ) ) {
				$unused_codes[] = $code;
				--$needed;
			}
		}
		update_option( self::OPTION_UNUSED_CODES, $unused_codes );
		return $count;
	}

	/**
	 * Set the user code
	 *
	 * @param int $user_id User ID
	 * @param string $code Code
	 * @return string
	 */
	public function get_code( int $user_id ): string {
		$meta_value = get_user_meta( $user_id, self::USER_CODE_KEY, true );
		if ( $meta_value ) {
			return $meta_value;
		}

		$codes = $this->unused_codes();
		if ( count( $codes ) === 0 ) {
			$this->generate_codes();
			$codes = $this->unused_codes();
		}
		$code = array_shift( $codes );
		update_user_meta( $user_id, self::USER_CODE_KEY, $code );
		update_option( self::OPTION_UNUSED_CODES, $codes );
		wp_cache_delete( self::CACHE_ALL_KEY, PLUGIN_SLUG );
		return $code;
	}

	/**
	 * Get unused codes.
	 *
	 * @return array
	 */
	public function unused_codes(): array {
		return get_option( self::OPTION_UNUSED_CODES, [] );
	}

	/**
	 * Get used codes.
	 *
	 * @return array
	 */
	public function used_codes(): array {
		$cached = wp_cache_get( self::CACHE_ALL_KEY, PLUGIN_SLUG );
		if ( $cached ) {
			return $cached;
		}
		global $wpdb;
		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = %s", self::USER_CODE_KEY ) );
		wp_cache_set( self::CACHE_ALL_KEY, $codes, PLUGIN_SLUG );
		return $codes;
	}

	/**
	 * Get all codes.
	 *
	 * @return array
	 */
	public function all_codes(): array {
		$unused = $this->unused_codes();
		$used = $this->used_codes();
		return array_merge( $unused, $used );
	}

	protected function event_url( string $path ): string {
		return sprintf(
			"https://forbiddentickets.com{$path}",
			$this->admin->get_setting( 'producer_slug' )->get()
		);
	}
}
