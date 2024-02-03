<?php declare( strict_types=1 );
// @phpcs:disable WordPress.Security.NonceVerification

namespace Transgression\Modules;

use Transgression\Admin\Option;
use Transgression\Admin\Page_Options;
use Transgression\Logger;
use Transgression\Modules\Email\Emailer;

use function Transgression\error_code_redirect;
use function Transgression\get_safe_post;
use function Transgression\load_view;

use const Transgression\PLUGIN_ROOT;
use const Transgression\PLUGIN_SLUG;
use const Transgression\PLUGIN_VERSION;

class ForbiddenTickets extends Module {
	public const USER_CODE_KEY = PLUGIN_SLUG . '_code';
	public const OPTION_UNUSED_CODES = PLUGIN_SLUG . '_unused_codes';
	public const CACHE_ALL_KEY = 'all_codes';

	public const UNUSED_COUNT = 10;

	protected Page_Options $admin;

	public function __construct( protected Emailer $emailer ) {
		parent::__construct();

		$this->admin();

		$this->emailer->add_template(
			'tickets',
			'Ticket email',
			'An email with the user\'s ticket code. Use the shortcode [code] to include the code in the email.'
		);
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$this->emailer->add_shortcode( 'code', fn() => sprintf(
				'<a href="%s"><code>%s</code></a>',
				$this->user_ticket_url( $user_id ),
				$this->get_code( $user_id ),
			) );
		}

		add_action( 'user_register', [ $this, 'set_user_code' ] );
		add_action( 'profile_update', [ $this, 'set_user_code' ] );
		add_filter( 'allowed_redirect_hosts', [ $this, 'filter_ft_host' ] );
		add_action( 'template_redirect', [ $this, 'handle_send_email' ] );
	}

	public function init() {
		wp_register_style(
			'transgression-blocks-tickets-style',
			plugin_dir_url( PLUGIN_ROOT ) . 'transgression/blocks/tickets/style.css',
			[],
			PLUGIN_VERSION,
		);
		wp_register_script(
			'transgression-blocks-tickets-editor',
			plugin_dir_url( PLUGIN_ROOT ) . 'transgression/blocks/tickets/index.js',
			[ 'wp-blocks' ],
			PLUGIN_VERSION,
			true,
		);
		register_block_type(
			PLUGIN_ROOT . '/blocks/tickets',
			[
				'render_callback' => [ $this, 'render_tickets_block' ],
			]
		);
	}

	protected function admin() {
		$admin = new Page_Options( 'forbidden_tickets', 'Forbidden Tickets', 'Forbidden Tickets', [], 'manage_options' );
		$this->admin = $admin;
		$admin->add_action( 'generate_codes', [ $this, 'action_generate_codes' ] );
		$admin->as_page( 'ticket.svg', 56 );
		$admin->add_style( 'forbidden-tickets' );
		$admin->add_script( 'forbidden-tickets' );

		( new Option( 'current_event', 'Current event url' ) )
			->of_type( 'url' )
			->describe( 'The Forbidden Tickets event URL' )
			->on_page( $admin );

		( new Option( 'copy_codes', 'Event codes' ) )
			->of_type( 'none' )
			->render_after( [ $this, 'render_copy' ] )
			->on_page( $admin );

		( new Option( 'remaining_codes', 'Remaining codes' ) )
			->of_type( 'none' )
			->render_after( [ $this, 'render_remaining' ] )
			->on_page( $admin );

		( new Option( 'producer_slug', 'Landing URL' ) )
			->describe( 'The Forbidden Tickets producer slug' )
			->on_page( $admin );

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
	 * Get the user ticket URL
	 *
	 * @param int $user_id User ID
	 * @return string
	 */
	public function user_ticket_url( int $user_id ): string {
		$code = $this->get_code( $user_id );
		$event_url = $this->admin->get_setting( 'current_event' )->get();
		if ( ! $event_url ) {
			$event_url = $this->event_url( '/events/%s' );
		}
		return add_query_arg( 'code', $code, $event_url );
	}

	/**
	 * Handle the send email action
	 *
	 * @return void
	 */
	public function handle_send_email(): void {
		$email = get_safe_post( 'tickets-email' );
		if ( ! $email ) {
			return;
		}

		$key = "trans-ticket-{$email}";
		if ( get_transient( $key ) ) {
			Logger::error( "Already sent ticket code to {$email}" );
			error_code_redirect( 203 );
		}
		set_transient( "trans-ticket-{$email}", true, MINUTE_IN_SECONDS * 5 );

		if ( ! is_email( $email ) ) {
			Logger::error( "Invalid email {$email} for tickets" );
			error_code_redirect( 201 );
		}

		$user_id = email_exists( $email );
		if ( ! $user_id ) {
			Logger::error( "Unrecognized {$email} for tickets" );
			error_code_redirect( 202 );
		}

		Logger::info( "Sending ticket code to {$email} for user {$user_id}" );
		$this->emailer->create()
			->to_user( $user_id )
			->with_subject( 'Your ticket code' )
			->with_template( 'tickets' )
			->set_shortcode( 'code', fn() => sprintf(
				'<a href="%s"><code>%s</code></a>',
				$this->user_ticket_url( $user_id ),
				$this->get_code( $user_id ),
			) )
			->send();
		error_code_redirect( 200 );
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

	/**
	 * Render the copy codes
	 *
	 * @return void
	 */
	public function render_copy() {
		$all_codes = $this->all_codes();
		printf(
			'<div class="flex-row">
				<code id="codes">%s<span class="dashicons dashicons-editor-paste-text"></span></code>
				<p id="result" class="hidden copy-success">
					Successfully copied %d codes!&nbsp;
					<a href="%s" target="_blank">Paste in an event</a>
				</p>
			</div>',
			esc_textarea( implode( ',', $all_codes ) ),
			count( $all_codes ),
			esc_url( $this->event_url( '/panel/pages/events+%s?tab=events' ) )
		);
	}

	/**
	 * Render the tickets block
	 *
	 * @return string
	 */
	public function render_tickets_block(): string {
		$code = '';
		if ( is_user_logged_in() ) {
			$code = $this->get_code( get_current_user_id() );
		}
		$tickets_url = $this->user_ticket_url( get_current_user_id() );
		$error_code = absint( $_GET['error_code'] ?? '' );
		ob_start();
		load_view( 'forbidden-tickets/login', compact( 'code', 'error_code', 'tickets_url' ) );
		return ob_get_clean();
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
		if ( $needed <= 0 ) {
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

		return $this->set_user_code( $user_id );
	}

	/**
	 * Set the user code
	 *
	 * @param int $user_id User ID
	 * @return string
	 */
	public function set_user_code( int $user_id ): ?string {
		$codes = $this->unused_codes();
		$code = array_shift( $codes );
		if ( ! $code ) {
			return null;
		}
		update_user_meta( $user_id, self::USER_CODE_KEY, $code );
		wp_cache_delete( self::CACHE_ALL_KEY, PLUGIN_SLUG );
		return $code;
	}

	/**
	 * Filter the Forbidden Tickets host for wp_safe_redirect
	 *
	 * @param array $hosts
	 * @return array
	 */
	public function filter_ft_host( array $hosts ): array {
		$hosts[] = 'forbiddentickets.com';
		return $hosts;
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

	/**
	 * Get the event URL
	 *
	 * @param string $path Path
	 * @return string
	 */
	protected function event_url( string $path ): string {
		return sprintf(
			"https://forbiddentickets.com{$path}",
			$this->admin->get_setting( 'producer_slug' )->get()
		);
	}
}
