<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\Option;
use Transgression\Admin\Option_Textarea;

class WPMail extends Email {
	public function send() {
		$body = '';
		if ( $this->template ) {
			$option = $this->emailer->get_template( $this->template );
			$body = $option->get();
			if ( ! $this->subject ) {
				$this->subject = get_option( "{$option->key}_subject", $option->label );
			}
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
		add_action( "option_{$key}_after_register", [ __CLASS__, 'register_subject' ], 10, 3 );
		$render_subject = function() use ( $key, $name ) {
			printf(
				'<input id="%1$s" class="large-text" type="text" name="%1$s" value="%2$s" placeholder="Subject" />',
				esc_attr( "{$key}_subject" ),
				esc_attr( get_option( "{$key}_subject", $name ) ),
			);
		};
		return ( new Option_Textarea( $key, $name ) )->render_before( $render_subject );
	}

	/**
	 * Registers the subject key with default.
	 *
	 * @param Option $option
	 * @param string $page
	 * @param string $group
	 * @return void
	 */
	public static function register_subject( Option $option, string $page, string $group ): void {
		register_setting(
			$group,
			"{$option->key}_subject",
			[ 'sanitize_callback' => 'sanitize_text_field' ]
		);
	}
}
