<?php declare( strict_types=1 );

namespace Transgression\Modules\Email;

use Transgression\Admin\{Option, Page};
use Transgression\Modules\Applications;

class Emailer {
	protected array $templates = [];

	protected Page $admin;

	public function __construct() {
		$admin = new Page( 'emails' );
		$this->admin = $admin;

		$admin->as_post_subpage( Applications::POST_TYPE, 'emails', 'Emails' );
		$admin->add_action( 'test-email', [ $this, 'do_test_email' ] );
		$admin->add_action( 'email-result', [ $this, 'handle_email_result' ] );

		$admin->with_description( call_user_func( [ $this->get_email_class(), 'admin_description' ] ) );
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
		if ( $email_class === MailPoet::class ) {
			return new MailPoet( $this, $to, $subject );
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
		$option = call_user_func( [ $this->get_email_class(), 'template_option' ], $key, $name );
		$this->templates[ $key ] = $option
			->describe( $description )
			->render_after( [$this, 'render_test_button'] )
			->on_page( $this->admin );
		return $option;
	}

	/**
	 * Checks if a template of a given key is set.
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function is_template( string $key ): bool {
		return isset( $this->templates[ $key ] );
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
		$result = null;
		$extra = [];
		if ( ! $this->is_template( $template_key ) ) {
			$result = 'invalid';
		}

		$user_id = get_current_user_id();
		$email = $this->create();
		try {
			$email
				->to_user( $user_id )
				->with_template( $template_key )
				->set_url( 'login-url', wc_get_page_permalink( 'shop' ) )
				->send();
			$result = 'success';
		} catch ( \Error $error ) {
			$result = 'error';
			$extra = [ 'error' => base64_encode( $error->getMessage() ) ];
		}

		if ( ! $result ) {
			return;
		}
		$redirect_url = $this->admin->get_url( array_merge( [
			'email-result' => $result,
			'_wpnonce' => wp_create_nonce( "email-result-{$result}" ),
		], $extra ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_email_result( string $email_result ): void {
		check_admin_referer( "email-result-{$email_result}" );
		if ( $email_result === 'invalid' ) {
			$this->admin->add_message( 'Could not find template' );
		} else if ( $email_result === 'success' ) {
			$user = get_userdata( get_current_user_id() );
			$this->admin->add_message( "Email sent to {$user->user_email}!", 'success' );
		} else if ( $email_result === 'error' ) {
			$error_msg = base64_decode( $_GET['error'] );
			$this->admin->add_message( "Error sending email: {$error_msg}" );
		}
	}

	protected function get_email_class(): string {
		if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
			return MailPoet::class;
		}
		return WPMail::class;
	}
}
