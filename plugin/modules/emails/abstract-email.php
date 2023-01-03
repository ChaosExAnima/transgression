<?php declare( strict_types = 1 );

namespace Transgression\Modules\Email;

use Error;
use Transgression\Admin\Option;
use WP_User;

abstract class Email {
	protected ?string $template = null;
	protected ?WP_User $user = null;
	protected array $shortcodes = [];

	/**
	 * Creates an email
	 *
	 * @param string|null $email
	 * @param string|null $subject
	 */
	public function __construct( protected Emailer $emailer, public ?string $email = null, public ?string $subject = null ) {}

	/**
	 * Sets the email via user ID
	 *
	 * @param int $user_id
	 * @return self
	 */
	public function to_user( int $user_id ): self {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new Error( "Could not find user with ID {$user_id}" );
		}

		$this->user = $user;
		$this->email = $user->user_email;
		return $this;
	}

	public function with_subject( string $subject ): self {
		$this->subject = esc_html( $subject );
		return $this;
	}

	public function set_url( string $key, string $url ): self {
		$this->shortcodes[ $key ] = $url;
		return $this;
	}

	public function with_template( string $template ): self {
		if ( ! $this->emailer->is_template( $template ) ) {
			throw new Error( "Could not find template of {$template}" );
		}
		$this->template = $template;
		return $this;
	}

	abstract public function send();

	public function do_shortcode( mixed $atts, ?string $content, string $tag ): string {
		if ( empty( $this->shortcodes[ $tag ] ) ) {
			return '';
		}

		$callback = $this->shortcodes[ $tag ];
		if ( is_callable( $callback ) ) {
			return call_user_func( $callback, $content, $atts );
		}

		if ( is_string( $callback ) ) {
			return sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $callback ),
				do_shortcode( $content ) ?? esc_url( $callback )
			);
		}
		return '';
	}

	protected function process_body( string $body ): string {
		$this->set_default_tags();

		global $shortcode_tags;
		$old_tags = $shortcode_tags;
		remove_all_shortcodes();

		foreach ( array_keys( $this->shortcodes ) as $tag ) {
			add_shortcode( $tag, [ $this, 'do_shortcode' ] );
		}

		$body = wp_kses_post( $body );
		$body = wpautop( $body );
		$body = wptexturize( $body );
		$body = do_shortcode( $body );

		remove_all_shortcodes();
		$shortcode_tags = $old_tags;

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
		if ( ! isset( $sc['events'] ) ) {
			$this->set_url( 'events', wc_get_page_permalink( 'shop' ) );
		}
	}

	/**
	 * Creates an admin option for templates
	 *
	 * @param string $key
	 * @param string $name
	 * @return Option
	 */
	abstract static public function template_option( string $key, string $name ): Option;

	/**
	 * Adds description in admin page header
	 *
	 * @return string
	 */
	public static function admin_description(): string {
		return '';
	}
}
