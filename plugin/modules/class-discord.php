<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\{Page_Options, Option};
use Transgression\{Logger, LoggerLevels};
use WP_Post;

class Discord extends Module {
	protected const ACTION_RESULT = 'hook-result';

	public function __construct( protected Page_Options $settings, Logger $logger ) {
		$settings->add_section( 'discord', 'Discord' );
		$settings->add_action( 'test-hook', [ $this, 'send_test' ] );
		$settings->add_action( self::ACTION_RESULT, [ $this, 'test_message' ] );
		$settings->add_settings( 'discord',
			( new Option( DiscordHooks::Application->hook(), 'Application Webhook' ) )
				->of_type( 'url' )
				->render_after( [ $this, 'render_test_button' ] )
		);

		$logging_hook = ( new Option( DiscordHooks::Logging->hook(), 'Logging Webhook' ) )
			->in_section( 'discord' )
			->of_type( 'url' )
			->render_after( [ $this, 'render_test_button' ] )
			->on_page( $settings );
		if ( $logging_hook->get() ) {
			$logger->register_destination( [ $this, 'send_logging_message' ] );
		}

		add_action( 'save_post_' . Applications::POST_TYPE, [ $this, 'send_app_message' ], 10, 3 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'send_woo_message' ] );
	}

	/**
	 * Sends message for applications.
	 *
	 * @param int $post_id The post ID
	 * @param WP_Post $post The post object
	 * @param bool $update True if this is an update
	 * @return void
	 */
	public function send_app_message( int $post_id, WP_Post $post, bool $update ): void {
		if ( $update ) {
			return; // Only for first insert
		}

		$description = sprintf(
			'There are %d open applications',
			absint( Applications::get_unreviewed_count() )
		);

		$this->send_discord_message(
			DiscordHooks::Application,
			"New application from {$post->post_title}",
			admin_url( "post.php?post={$post_id}&action=edit" ),
			$description
		);
	}

	/**
	 * Renders a button that triggers
	 *
	 * @param Option $option
	 * @return void
	 */
	public function render_test_button( Option $option ): void {
		$test_url = $this->settings->get_url( [
			'test-hook' => $option->key,
			'_wpnonce' => wp_create_nonce( "test-hook-{$option->key}" ),
		] );
		printf(
			'&nbsp;<a class="button button-secondary" href="%s" id="%s-test">Send test</a>',
			esc_url( $test_url ),
			esc_attr( $option->key )
		);
	}

	/**
	 * Sends a test message
	 *
	 * @param string $hook_type
	 * @return void
	 */
	public function send_test( string $hook_type ): void {
		check_admin_referer( "test-hook-{$hook_type}" );
		$url = null;
		try {
			$webhook = DiscordHooks::from_hook( $hook_type );
			$url = $this->settings->value( $webhook->hook() );
		} catch ( \ValueError $error ) {
			Logger::error( $error );
		}
		if ( ! $url ) {
			$this->settings->action_redirect( self::ACTION_RESULT, 'not-conf' );
		}

		$this->send_discord_message(
			$webhook,
			'Test message',
			site_url(),
			'This is a message to check things are working!'
		);
		$this->settings->action_redirect( self::ACTION_RESULT, 'success' );
	}

	/**
	 * Shows the hook test result message
	 *
	 * @param string $result
	 * @return void
	 */
	public function test_message( string $result ): void {
		if ( $result === 'not-conf' ) {
			$this->settings->add_message( 'Hook not configured' );
		} elseif ( $result === 'success' ) {
			$this->settings->add_message( 'Sent test message', 'success' );
		}
	}

	/**
	 * Sends logging error message
	 *
	 * @param string $message The stringified error message
	 * @param LoggerLevels $severity Error severity
	 * @param mixed $error Raw message object
	 * @return void
	 */
	public function send_logging_message( string $message, LoggerLevels $severity ) {
		$this->send_discord_message( DiscordHooks::Logging, $severity->value, null, $message, [], true );
	}

	/**
	 * Sends a message to Discord
	 *
	 * @param DiscordHooks $webhook
	 * @param string $title
	 * @param string|null $url
	 * @param string|null $content The description
	 * @param array $extra_fields
	 * @param bool $simple
	 * @return void
	 */
	protected function send_discord_message(
		DiscordHooks $webhook,
		string $title,
		?string $url = null,
		?string $content = null,
		array $extra_fields = [],
		bool $simple = false
	): void {
		$hook_url = $this->settings->value( $webhook->hook() );
		if ( ! $hook_url ) {
			return;
		}
		$body = [];
		if ( ! $simple ) {
			$embed = array_merge( $extra_fields, [
				'title' => esc_html( $title ),
				'type' => 'rich',
				'url' => $url,
				'description' => $content,
			] );
			$body['embeds'] = [ $embed ];
		} else {
			$body = $extra_fields;
			$body['content'] = "{$title}: {$content}";
		}

		$args = [
			'body' => wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'blocking' => false,
		];

		wp_remote_post( $hook_url, $args );
	}

}

enum DiscordHooks: string {
	case Application = 'app';
	case Logging = 'log';

	public function hook(): string {
		return "{$this->value}_discord_hook";
	}

	public static function from_hook( string $hook ): self {
		return DiscordHooks::from( substr( $hook, 0, 3 ) );
	}
}
