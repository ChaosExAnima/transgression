<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\{Option, Page};
use Transgression\Modules\Applications;

class Emailer {
	public const TEMPLATES = [
		'email_approved' => 'Approved Template',
		'email_denied' => 'Denied Template',
		'email_duplicate' => 'Duplicate Application Template',
		'email_login' => 'Login Template',
	];
	public array $templates = [];

	protected Page $admin;

	public function __construct() {
		$admin = new Page( 'emails' );
		$this->admin = $admin;

		$admin->as_post_subpage( Applications::POST_TYPE, 'emails', 'Emails' );
		$admin->add_action( 'test-email', [ $this, 'do_test_email' ] );

		$email = $this->create();
		$admin->with_description( $email->admin_description() );
		foreach ( self::TEMPLATES as $key => $name ) {
			$this->add_template( $key, $name );
		}

	}

	/**
	 * Gets an email class reference
	 *
	 * @param string|null $to
	 * @param string|null $subject
	 * @return Email
	 */
	public function create( ?string $to = null, ?string $subject = null ): Email {
		if ( is_plugin_active( 'mailpoet' ) ) {
			return new MailPoet( $to, $subject );
		}
		return new WPMail( $to, $subject );
	}

	public function add_template( string $key, string $name, string $description = '' ): Option {
		$email = $this->create();
		$option = $email->template_option( $key, $name );
		$this->templates[ $key ] = $option
			->describe( $description )
			->render_after( [$this, 'render_test_button'] )
			->on_page( $this->admin );
		return $option;
	}

	/**
	 * Renders a test email button
	 *
	 * @param Option $option
	 * @return void
	 */
	public function render_test_button( Option $option ): void {
		$test_url = $this->admin->get_url( [ 'test-email' => $option->key ] );
		printf(
			'&nbsp;<a class="button button-secondary" href="%s" id="%s-test">Send test</a>',
			esc_url( wp_nonce_url( $test_url, "test-email-{$option->key}" ) ),
			esc_attr( $option->key )
		);
	}

	/**
	 * Sends a test email for a template to the current user
	 *
	 * @param string $template_key
	 * @return void
	 */
	public function do_test_email( string $template_key ) {
		check_admin_referer( 'test-email-' . $template_key );
		if ( ! isset( self::TEMPLATES[ $template_key ] ) ) {
			$this->admin->add_message( 'Could not find template', 'error' );
			return;
		}

		$user_id = get_current_user_id();
		$email = $this->create();
		try {
			$email
				->to_user( $user_id )
				->with_subject( "Testing template {$template_key}" )
				->with_template( $template_key )
				->send();
		} catch ( \Error $error ) {
			$this->admin->add_message( $error->getMessage(), 'error' );
			return;
		}
		$user = get_userdata( $user_id );
		$this->admin->add_message( "Email sent to {$user->user_email}!", 'success' );
	}
}
