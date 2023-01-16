<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\Option;
use Transgression\Admin\Page;

class Auth0 extends Module {
	public function __construct( protected Page $settings ) {
		$this->settings->add_section( 'auth0', 'Auth0' );
		$this->settings->add_settings( 'auth0',
			( new Option( 'auth0_baseurl', 'Base URL' ) )->of_type( 'url' ),
			( new Option( 'auth0_token', 'Access Token' ) )->of_type( 'password' ),
			new Option( 'auth0_client', 'Client ID' )
		);
	}

	/**
	 * Performs a social login
	 *
	 * @param string $provider Whatever social login that is configured
	 * @return void
	 */
	public function social_redirect( string $provider ): void {
		$client_id =  $this->settings->value( 'auth0_client' );
		$base_url = $this->baseurl();
		if ( ! $base_url || ! $client_id ) {
			return;
		}

		$url = add_query_arg( [
			'response_type' => 'code',
			'client_id' => $client_id,
			'connection' => $provider,
			'redirect_uri' => '',
			'state' => '',
		], $base_url );
		wp_safe_redirect( $url );
		exit;
	}

	private function baseurl(): ?string {
		return $this->settings->value( 'auth0_baseurl' );
	}
}
