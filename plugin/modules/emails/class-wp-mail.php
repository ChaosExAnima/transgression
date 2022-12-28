<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option;

class WPMail extends Email {
	public function send() {
		wp_mail(
			$this->email,
			$this->subject,
			$this->process_body( $this->body )
		);
	}

	public function template_option( string $key, string $name ): Option {
		return ( new Option( $key, $name ) );
	}
}
