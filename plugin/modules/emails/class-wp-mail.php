<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option_Textarea;

class WPMail extends Email {
	public function send() {
		$body = '';
		if ( $this->template ) {
			$option = self::template_option( $this->template, '' );
			$body = $option->get();
		}
		if ( ! $body ) {
			throw new \Error( 'No body set' );
		}
		$headers = [
			sprintf( 'From: %s', sanitize_email( get_bloginfo( 'admin_email' ) ) ),
			'Content-type: text/html',
		];

		wp_mail(
			$this->email,
			$this->subject,
			$this->process_body( $body ),
			$headers
		);
	}

	/**
	 * @inheritDoc
	 */
	public static function admin_description(): string {
		return 'Template replacements: <code>[name]</code> the name of the person, ' .
			'<code>[events]text[/events]</code> for the events link';
	}

	/**
	 * @inheritDoc
	 */
	public static function template_option( string $key, string $name ): Option_Textarea {
		return ( new Option_Textarea( $key, $name ) );
	}
}
