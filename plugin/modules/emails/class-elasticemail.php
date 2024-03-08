<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option;
use Transgression\Admin\Option_Select;
use Transgression\Logger;

use function Transgression\prefix;

class ElasticEmail extends Email {
	protected const API_URL = 'https://api.elasticemail.com/v4';

	/**
	 * @inheritDoc
	 */
	protected function attempt_send(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public static function init( Emailer $emailer ): void {
		$admin = $emailer->admin;
		$url = $admin->get_url( [
			'_wpnonce' => wp_create_nonce( 'ee_purge_cache' ),
			'purge_cache' => '1',
		] );
		$admin->add_setting(
			( new Option( 'elastic_email_key', __( 'ElasticEmail Key', 'transgression' ) ) )
				->of_type( 'password' )
				->render_after( function() use ( $url ) {
					printf(
						' <a class="button" href="%s">Purge Cache</a>',
						esc_url( $url )
					);
				} )
		);
		$admin->add_action( 'purge_cache', function() use ( $admin ) {
			check_admin_referer( 'ee_purge_cache' );
			wp_cache_flush_group( prefix( 'elastic_email' ) );
			$admin->redirect_message( 'cache_purged' );
		} );
		$admin->register_message( 'cache_purged', __( 'Cache purged', 'transgression' ), 'success' );
	}

	/**
	 * @inheritDoc
	 */
	public static function template_option( string $key, string $name, Emailer $emailer ): Option {
		$api_key = $emailer->admin->value( 'elastic_email_key' );
		$fallback_option = new Option( $key, $name );
		if ( ! $api_key ) {
			return $fallback_option;
		}

		$templates = self::load_templates( $api_key );
		if ( ! count( $templates ) ) {
			Logger::error( 'Could not load ElasticEmail templates' );
			return $fallback_option;
		}

		return ( new Option_Select( $key, $name ) )
			->without_none()
			->with_options( $templates );
	}

	/**
	 * Loads the templates from the ElasticEmail API
	 *
	 * @param string $api_key
	 * @return array
	 */
	protected static function load_templates( string $api_key ): array {
		$cached = wp_cache_get( 'elastic_email_templates', prefix( 'elastic_email' ) );
		if ( is_array( $cached ) && count( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get( self::API_URL . '/templates?scopeType=personal', [
			'headers' => [
				'X-ElasticEmail-ApiKey' => $api_key,
			],
		] );
		if ( is_wp_error( $response ) ) {
			Logger::error( 'Invalid response from EE templates API' );
			return [];
		}
		$body = wp_remote_retrieve_body( $response );
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			Logger::error( "GET EE templates API {$status}: {$body}" );
			return [];
		}
		$json = json_decode( $body, true );
		if ( ! $json || ! is_array( $json ) ) {
			Logger::error( "Invalid JSON from EE templates API: {$body}" );
			return [];
		}

		$template_names = [ null ];
		foreach ( $json as $template ) {
			$template_names[] = $template['Name'];
		}
		wp_cache_set( 'elastic_email_templates', $template_names, prefix( 'elastic_email' ), DAY_IN_SECONDS );
		return $template_names;
	}
}
