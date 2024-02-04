<?php declare( strict_types = 1 );

namespace Transgression\Modules\Email;

use Error;
use Transgression\Admin\Option;
use Transgression\Logger;
use WP_User;

abstract class Email {
	protected ?string $template = null;
	protected ?WP_User $user = null;
	protected array $shortcodes = [];

	/**
	 * Creates an email
	 *
	 * @param Emailer     $emailer
	 * @param string|null $email
	 * @param string|null $subject
	 */
	public function __construct(
		protected Emailer $emailer,
		public ?string $email = null,
		public ?string $subject = null
	) {}

	/**
	 * Sets the email via user ID
	 *
	 * @param int $user_id
	 * @return self
	 */
	public function to_user( int $user_id ): self {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			Logger::error( "Could not find user with ID {$user_id}" );
		}

		$this->user = $user;
		$this->email = $user->user_email;
		return $this;
	}

	public function with_subject( string $subject ): self {
		$this->subject = esc_html( $subject );
		return $this;
	}

	public function set_shortcode( string $key, callable $content ): self {
		$this->shortcodes[ $key ] = $content;
		return $this;
	}

	public function set_url( string $key, string $url ): self {
		$this->shortcodes[ $key ] = $url;
		return $this;
	}

	public function with_template( string $template ): self {
		if ( ! $this->emailer->is_template( $template ) ) {
			Logger::error( new Error( "Could not find template of {$template}" ) );
		}
		$this->template = $template;
		return $this;
	}

	/**
	 * Sends an email
	 *
	 * @return void
	 */
	public function send() {
		try {
			if ( ! $this->email ) {
				throw new Error( 'Email is not set' );
			}
			$success = $this->attempt_send();
			if ( ! $success ) {
				throw new Error( "Could not send email to {$this->email}" );
			}
		} catch ( Error $error ) {
			Logger::error( $error );
		}
	}

	/**
	 * Attempts to send an email
	 *
	 * @return bool True if email was successfully sent
	 */
	abstract protected function attempt_send(): bool;

	public function do_shortcode( mixed $atts, ?string $content, string $tag ): string {
		if ( empty( $this->shortcodes[ $tag ] ) ) {
			return '';
		}

		$callback = $this->shortcodes[ $tag ];
		if ( is_callable( $callback ) ) {
			return call_user_func( $callback, $content, $atts );
		}

		if ( is_string( $callback ) ) {
			if ( $content ) {
				return sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $callback ),
					do_shortcode( $content ) ?? esc_url( $callback )
				);
			}
			return esc_url( $callback );
		}
		return '';
	}

	protected function process_body( string $body, bool $do_wp_stuff = true ): string {
		$this->set_default_tags();

		global $shortcode_tags;
		$old_tags = $shortcode_tags;
		remove_all_shortcodes();

		foreach ( array_keys( $this->shortcodes ) as $tag ) {
			add_shortcode( $tag, [ $this, 'do_shortcode' ] );
		}

		if ( $do_wp_stuff ) {
			$body = wp_kses_post( $body );
			$body = wpautop( $body );
			$body = wptexturize( $body );
		}
		$body = do_shortcode( $body );

		remove_all_shortcodes();
		$shortcode_tags = $old_tags; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		return $body;
	}

	protected function set_default_tags(): void {
		$sc = $this->shortcodes;
		if ( ! isset( $sc['name'] ) ) {
			$this->shortcodes['name'] = function (): string {
				if ( $this->user ) {
					return $this->user->display_name;
				}
				return 'there';
			};
		}

		$emailer_codes = $this->emailer->get_shortcodes();
		foreach ( $emailer_codes as $key => $content ) {
			if ( isset( $sc[ $key ] ) ) {
				continue;
			}
			$this->shortcodes[ $key ] = $content;
		}
	}

	protected function get_headers( bool $is_html = true ): array {
		$headers = [ sprintf( 'From: %s', sanitize_email( get_bloginfo( 'admin_email' ) ) ) ];
		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		return $headers;
	}

	/**
	 * Initialization. Runs on Emailer start, defaults to no-op.
	 *
	 * @param Emailer $emailer
	 * @return void
	 */
	public static function init( Emailer $emailer ): void {}

	/**
	 * Creates an admin option for templates
	 *
	 * @param string $key
	 * @param string $name
	 * @return Option
	 */
	abstract public static function template_option( string $key, string $name ): Option;
}
