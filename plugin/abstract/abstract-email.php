<?php declare( strict_types = 1 );

namespace Transgression\Abstract;

use Error;

abstract class Email {
	protected string $body;

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

	abstract public function send();
}
