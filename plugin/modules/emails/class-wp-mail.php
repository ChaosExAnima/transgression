<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option_Textarea;

class WPMail extends Email {
	public function send() {
		$body = '';
		if ( $this->template ) {
			$option = $this->template_option( $this->template, '' );
			$body = $option->get();
		}
		if ( ! $body ) {
			return;
		}
		$headers = [
			sprintf( 'From: %s', sanitize_email( get_bloginfo( 'admin_email' ) ) ),
		];

		wp_mail(
			$this->email,
			$this->subject,
			$this->process_body( $body ),
			$headers
		);
	}

	public function admin_description(): string {
		return 'Template replacements: <code>[name]</code> the name of the person, ' .
			'<code>[events]text[/events]</code> for the events link';
	}

	public function template_option( string $key, string $name ): Option_Textarea {
		return ( new Option_Textarea( $key, $name ) );
	}

	protected function process_body( string $body ): string {
		// TODO: Clear global shortcodes, register new ones, process body.
		// Alternatively: handle easier route to do fake shortcodes?
		return $body;
	}
}
