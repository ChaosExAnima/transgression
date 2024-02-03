<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\{Option, Page_Options};
use Transgression\Logger;

use const Transgression\PLUGIN_ROOT;

use function Transgression\{error_code_redirect, get_current_url, strip_query};

class Auth0 extends Module {
	public const PROVIDERS = [ 'discord', 'google-oauth2' ];

	public function __construct( protected People $people, protected Page_Options $settings, protected ForbiddenTickets $tickets ) {
		$this->settings->add_section( 'auth0', 'Auth0' );
		$this->settings->add_settings( 'auth0',
			( new Option( 'auth0_baseurl', 'Base URL' ) )->of_type( 'url' ),
			new Option( 'auth0_client', 'Client ID' ),
			( new Option( 'auth0_token', 'Access Token' ) )->of_type( 'password' ),
		);

		if ( ! $this->is_configured() ) {
			return;
		}

		add_filter( 'transgression_social_configured', '__return_true' );
		add_action( 'transgression_social_login', [ $this, 'display_login_buttons' ] );
		add_action( 'template_redirect', [ $this, 'handle_login' ] );
	}

	/**
	 * Displays login buttons. Called via action.
	 *
	 * @param bool $enabled
	 * @return void
	 */
	public function display_login_buttons() {
		if ( ! $this->is_configured() ) {
			return;
		}
		foreach ( self::PROVIDERS as $provider ) {
			printf(
				'<a href="?social-login=%1$s" class="social-login %1$s">',
				esc_attr( $provider )
			);
			if ( file_exists( PLUGIN_ROOT . "/assets/icons/{$provider}.svg" ) ) {
				include PLUGIN_ROOT . "/assets/icons/{$provider}.svg";
			} else {
				echo esc_html( $provider );
			}
			echo '</a>';
		}
	}

	/**
	 * Handles logging in via social flows
	 *
	 * @return void
	 */
	public function handle_login() {
		// Skip for logged in users.
		if ( is_user_logged_in() ) {
			return;
		}

		// Checks minimum requirements
		if (
			empty( $_GET['social-login'] ) &&
			empty( $_GET['code'] ) &&
			empty( $_GET['state'] ) ||
			! empty( $_GET['error_code'] )
		) {
			return;
		}

		$current_url = strip_query( get_current_url() );

		// Do provider login via Auth0 redirect
		if ( isset( $_GET['social-login'] ) ) {
			$provider = sanitize_text_field( wp_unslash( $_GET['social-login'] ) );
			if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
				Logger::info( "Invalid social login: {$provider}" );
				error_code_redirect( 101, $current_url );
			}
			$this->social_redirect( $provider, $current_url );
			return;
		}

		if ( empty( $_GET['code'] ) && empty( $_GET['state'] ) ) {
			return;
		}

		// Check if state is okay
		$state = json_decode( base64_decode( wp_unslash( $_GET['state'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if (
			! $state ||
			! is_array( $state ) ||
			count( $state ) !== 3
		) {
			Logger::info( 'Got invalid social login state' );
			error_code_redirect( 102, $current_url );
		}

		// Validate nonce
		$current_url = $state[1];
		if ( 1 !== wp_verify_nonce( $state[2], $this->get_nonce_action( $state[0], $state[1] ) ) ) {
			Logger::info( 'Nonce validation failed for social login' );
			error_code_redirect( 103, $current_url );
		}

		// Gets the email from the code
		$email = $this->get_email( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
		if ( ! $email ) {
			error_code_redirect( 104, $current_url );
		}

		// Validates the user exists
		$user_id = email_exists( $email );
		if ( ! $user_id ) {
			Logger::error( "Failed social login attempt by email {$email}" );
			error_code_redirect( 105, $current_url );
		}

		Logger::info( "Social login by user {$user_id} with email {$email}" );

		// Log the user in and redirect to get tickets
		wp_set_auth_cookie( $user_id, true );
		$event_url = $this->tickets->user_ticket_url( $user_id );
		wp_safe_redirect( $event_url );
		exit;
	}

	/**
	 * Checks if all fields are set correctly
	 *
	 * @return boolean
	 */
	private function is_configured(): bool {
		return (
			$this->settings->value( 'auth0_baseurl' ) &&
			$this->settings->value( 'auth0_token' ) &&
			$this->settings->value( 'auth0_client' )
		);
	}

	/**
	 * Performs a social login
	 *
	 * @param string $provider Whatever social login that is configured
	 * @param string|null $destination Where to send the user after redirecting
	 * @return void
	 */
	private function social_redirect( string $provider, ?string $destination = null ): void {
		$client_id = $this->settings->value( 'auth0_client' );
		$base_url = $this->baseurl( 'authorize' );
		if ( ! $base_url || ! $client_id ) {
			return;
		}

		$state = [ $provider ];
		if ( $destination ) {
			$state[] = $destination;
		}
		$state[] = wp_create_nonce( $this->get_nonce_action( $provider, $destination ) );

		$url = add_query_arg( [
			'scope' => 'openid email',
			'response_type' => 'code',
			'client_id' => $client_id,
			'connection' => $provider,
			'redirect_uri' => home_url(),
			'state' => base64_encode( wp_json_encode( $state ) ),
		], $base_url );
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Gets the user email from Auth0
	 *
	 * @param string $code The provided code
	 * @return string|null The email or null when something fails
	 */
	private function get_email( string $code ): ?string {
		if ( ! $code ) {
			Logger::error( 'Trying to get profile with empty token' );
			return null;
		}

		$args = [
			'headers' => 'Content-Type: application/x-www-form-urlencoded',
			'body' => http_build_query( [
				'grant_type' => 'authorization_code',
				'client_id' => $this->settings->value( 'auth0_client' ),
				'client_secret' => $this->settings->value( 'auth0_token' ),
				'code' => $code,
				'redirect_uri' => home_url(),
			] ),
		];
		$response = wp_remote_post( $this->baseurl( 'oauth/token' ), $args );
		$json = $this->parse_response( $response );

		if ( empty( $json['access_token'] ) ) {
			Logger::error( 'Did not get access token from Auth0' );
			return null;
		}

		$args = [
			'headers' => "Authorization: Bearer {$json['access_token']}",
		];
		$response = wp_remote_get( $this->baseurl( 'userinfo' ), $args );
		$json = $this->parse_response( $response );

		if ( empty( $json['email'] ) ) {
			Logger::error( 'User info from Auth0 did not contain email' );
			return null;
		}

		return sanitize_email( $json['email'] );
	}

	/**
	 * Gets Auth0 URL
	 *
	 * @param string $path Path to append, without slash prefix
	 * @return string|null Full URL, or null if baseurl isn't set
	 */
	private function baseurl( string $path = '' ): ?string {
		$base = $this->settings->value( 'auth0_baseurl' );
		if ( ! $base ) {
			return null;
		}
		return "{$base}/{$path}";
	}

	/**
	 * Gets the nonce action
	 *
	 * @param string $provider
	 * @param string $destination
	 * @return string
	 */
	private function get_nonce_action( string $provider, string $destination = '' ): string {
		return "nonce_auth0_state_{$provider}_{$destination}";
	}

	/**
	 * Check response from server
	 *
	 * @param array|\WP_Error $response The remote response
	 * @return mixed|null
	 */
	private function parse_response( array|\WP_Error $response ): mixed {
		if ( is_wp_error( $response ) ) {
			Logger::error( $response );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			Logger::error( sprintf(
				'Got bad status from Auth0: %d %s',
				wp_remote_retrieve_response_code( $response ),
				$body
			) );
			return null;
		}
		$json = json_decode( $body, true );
		if ( $json === null ) {
			Logger::error( 'Could not decode Auth0 body: ' . $body );
			return null;
		}

		return $json;
	}
}
