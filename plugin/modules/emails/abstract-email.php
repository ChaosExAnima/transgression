<?php declare( strict_types = 1 );

namespace Transgression\Modules\Email;

use Error;
use Transgression\Admin\Option;

abstract class Email {
	protected ?string $template = null;
	protected bool $is_html = false;
	protected array $replace_urls = [];

	/**
	 * Creates an email
	 *
	 * @param string|null $email
	 * @param string|null $subject
	 */
	public function __construct( public ?string $email = null, public ?string $subject = null ) {}

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

		$this->email = $user->user_email;
		return $this;
	}

	public function with_subject( string $subject ): self {
		$this->subject = esc_html( $subject );
		return $this;
	}

	public function set_url( string $key, string $url ): self {
		$this->replace_urls["[{$key}]"] = $url;
		return $this;
	}

	public function with_template( string $template ): self {
		if ( !isset( Emailer::TEMPLATES[$template] ) ) {
			throw new Error( "Could not find template of {$template}" );
		}
		$this->template = $template;
		return $this;
	}

	abstract public function send();

	/**
	 * Creates an admin option for templates
	 *
	 * @param string $key
	 * @param string $name
	 * @return Option
	 */
	abstract public function template_option( string $key, string $name ): Option;

	/**
	 * Adds description in admin page header
	 *
	 * @return string
	 */
	public function admin_description(): string {
		return '';
	}

	protected function process_body( string $body ): string {
		return str_replace(
			array_keys( $this->replace_urls ),
			array_values( $this->replace_urls ),
			$body
		);
	}
}
