<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\{Option, Page_Options};
use Transgression\Logger;
use Transgression\Modules\Applications;

class Emailer {
	/** @var Option[] */
	protected array $templates = [];

	public Page_Options $admin;

	public function __construct() {
		$admin = new Page_Options( 'emails', 'Emails' );
		$this->admin = $admin;
		call_user_func( [ $this->get_email_class(), 'init' ], $this );

		if ( ! is_admin() ) {
			return;
		}

		$admin->as_subpage( 'options-general.php' );
		$admin->add_action( 'test-email', [ $this, 'do_test_email' ] );

		$admin->register_message( 'template_invalid', 'Could not find template' );
		$user = get_userdata( get_current_user_id() );
		if ( $user ) {
			$admin->register_message( 'test_sent', "Email sent to {$user->user_email}!", 'success' );
		}
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// phpcs:ignore WordPress.Security
			$error_msg = base64_decode( wp_unslash( $_GET['error'] ) );
			$admin->register_message( 'test_error', "Error sending email: {$error_msg}" );
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
		$email_class = $this->get_email_class();
		try {
			if ( $email_class === MailPoet::class ) {
				return new MailPoet( $this, $to, $subject );
			}
		} catch ( \Error $error ) {
			Logger::error( $error );
		}
		return new WPMail( $this, $to, $subject );
	}

	/**
	 * Registers an email template.
	 *
	 * @param string $key
	 * @param string $name
	 * @param string $description
	 * @return Option
	 */
	public function add_template( string $key, string $name, string $description = '' ): Option {
		/** @var Option */
		$option = call_user_func( [ $this->get_email_class(), 'template_option' ], $key, $name );
		$this->templates[ $key ] = $option
			->describe( $description )
			->render_after( [ $this, 'render_test_button' ] )
			->on_page( $this->admin );
		return $option;
	}

	/**
	 * Gets a template for a given key.
	 *
	 * @param string $key
	 * @return Option
	 */
	public function get_template( string $key ): Option {
		return $this->templates[ $key ];
	}

	/**
	 * Checks if a template of a given key is set.
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function is_template( string $key ): bool {
		// phpcs:ignore WordPress.WhiteSpace.OperatorSpacing
		return isset( $this->templates[ $key ] ) && !! $this->templates[ $key ]->get();
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
	public function do_test_email( string $template_key ): void {
		check_admin_referer( 'test-email-' . $template_key );
		if ( ! $this->is_template( $template_key ) ) {
			$this->admin->redirect_message( 'template_invalid' );
		}

		try {
			$user_id = get_current_user_id();
			$this->create()
				->to_user( $user_id )
				->with_template( $template_key )
				->set_url( 'login-url', wc_get_page_permalink( 'shop' ) )
				->send();
			$this->admin->redirect_message( 'test_sent' );
		} catch ( \Error $error ) {
			Logger::error( $error );
			$this->admin->redirect_message( 'test_error', [ 'error' => base64_encode( $error->getMessage() ) ] );
		}

		$this->admin->redirect_message( 'test_error', [ 'error' => base64_encode( 'Could not send test email' ) ] );
	}

	/**
	 * Gets the class string of the email to use..
	 *
	 * @return string
	 */
	protected function get_email_class(): string {
		if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
			return MailPoet::class;
		}
		return WPMail::class;
	}
}
