<?php declare( strict_types=1 );

namespace Transgression\Modules;

use Transgression\Admin\{Page_Options, Option, Option_Select};
use Transgression\{Logger, LoggerLevels};
use WP_Post;

class Discord extends Module {
	public function __construct( protected Page_Options $settings, Logger $logger ) {
		$settings->register_message( 'not_conf', __( 'Hook not configured', 'transgression' ) );
		$settings->register_message( 'test_success', __( 'Test message sent', 'transgression' ) );

		$settings->add_section( 'discord', 'Discord' );
		$apps_hook = ( new Option( DiscordHooks::Application->hook(), 'Application Webhook' ) )
			->of_type( 'url' )
			->in_section( 'discord' );
		$settings->add_button(
			$apps_hook,
			__( 'Send test', 'transgression' ),
			[ $this, 'send_test' ]
		);

		$logging_hook = ( new Option( DiscordHooks::Logging->hook(), 'Logging Webhook' ) )
			->of_type( 'url' )
			->in_section( 'logging' );
		$settings->add_button(
			$logging_hook,
			__( 'Send test', 'transgression' ),
			[ $this, 'send_test' ]
		);
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
	 * Sends a test message
	 *
	 * @param string $value The value passed.
	 * @param Option $option The triggering option.
	 * @return void
	 */
	public function send_test( string $value, Option $option ): string {
		$url = null;
		try {
			$webhook = DiscordHooks::from_hook( $option->key );
			$url = $this->settings->value( $webhook->hook() );
		} catch ( \ValueError $error ) {
			Logger::error( $error );
		}
		if ( ! $url ) {
			return 'not_conf';
		}

		$this->send_discord_message(
			$webhook,
			'Test message',
			site_url(),
			'This is a message to check things are working!'
		);
		return 'test_sent';
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
		$this->send_discord_message( DiscordHooks::Logging, $severity->name(), null, $message, [], true );
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
