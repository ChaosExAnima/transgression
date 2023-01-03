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
	public function do_test_email( string $template_key ) {
		check_admin_referer( 'test-email-' . $template_key );
		if ( ! $this->is_template( $template_key ) ) {
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
				->set_url( 'login-url', wc_get_page_permalink( 'shop' ) )
				->send();
		} catch ( \Error $error ) {
			$this->admin->add_message( $error->getMessage(), 'error' );
			return;
		}
		$user = get_userdata( $user_id );
		$this->admin->add_message( "Email sent to {$user->user_email}!", 'success' );
	}

	protected function get_email_class(): string {
		if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
			return MailPoet::class;
		}
		return WPMail::class;
	}
}
