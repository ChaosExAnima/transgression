<?php declare( strict_types=1 );

namespace Transgression;

use WP_Error;

class Emails extends Singleton {
	const ADMIN_PAGE = 'edit.php?post_type=' . Applications::POST_TYPE;
	const EMAIL_PAGE = 'apps_emails';
	const SETTING_GROUP = 'transgression_emails';
	const SETTING_SECTION = self::SETTING_GROUP . '_emails';
	const SETTING_ERRORS = self::SETTING_GROUP . '_messages';
	const TEMPLATES = [
		'email_approved' => 'Approved Template',
		'email_denied' => 'Denied Template',
		'email_duplicate' => 'Duplicate Application Template',
	];

	private string $admin_email_page = '';

	/** @var ?\MailPoet\Newsletter\NewslettersRepository */
	private $newsletter_repo = null;

	public function init() {
		if ( !class_exists( '\\MailPoet\\DI\\ContainerWrapper' ) ) {
			return;
		}

		add_filter( 'wp_mail_from', [ $this, 'filter_from' ] );
		add_action( 'admin_menu', [$this, 'action_admin_menu'] );
		add_action( 'admin_init', [$this, 'action_admin_init'] );

		add_filter( 'mailpoet_newsletter_shortcode', [$this, 'filter_shortcodes'], 10, 3 );

		$this->newsletter_repo = $this->get_mp_instance(
			\MailPoet\Newsletter\NewslettersRepository::class
		);
	}

	public function send_email( string $email, string $template_key ): ?WP_Error {
		if ( !isset( self::TEMPLATES[$template_key] ) ) {
			return new WP_Error( 'email-no-template', 'Template not found' );
		}
		if ( !$this->newsletter_repo ) {
			return new WP_Error( 'email-no-mailpoet', 'MailPoet not activated' );
		}
		$template_id = get_option( $template_key, 0 );
		if ( !$template_id ) {
			return new WP_Error( 'email-template-unset', 'Template not set' );
		}
		/** @var ?\MailPoet\Entities\NewsletterEntity */
		$template = $this->newsletter_repo->findOneById( $template_id );
		if ( !$template ) {
			return new WP_Error( 'email-template-missing', 'Template not found' );
		}

		try {
			/** @var \MailPoet\Newsletter\Preview\SendPreviewController */
			$preview_controller = $this->get_mp_instance(
				\MailPoet\Newsletter\Preview\SendPreviewController::class
			);
			$preview_controller->sendPreview( $template, $email );
		} catch ( \Throwable $error ) {
			log_error( new WP_Error( $error->getMessage(), $error->getTraceAsString() ) );
			return new WP_Error( 'send-fail', "There was a problem sending the mail to {$email}" );
		}

		return null;
	}

	public function send_user_email( int $user_id, string $template_key ): ?WP_Error {
		$user = get_userdata( $user_id );
		if ( !$user ) {
			return new WP_Error( 'email-no-user', 'User not found', compact( 'user_id' ) );
		}
		return $this->send_email( $user->user_email, $template_key );
	}

	/**
	 * Filters MailPoet shortcodes.
	 *
	 * @param string $shortcode
	 * @param \MailPoet\Entities\NewsletterEntity $newsletter
	 * @param \MailPoet\Entities\SubscriberEntity $subscriber
	 * @return string
	 */
	public function filter_shortcodes( string $shortcode, $newsletter, $subscriber ): string {
		if ( $shortcode !== '[link:confirm_email]' ) {
			return $shortcode;
		}

		/** @var \MailPoet\Subscription\SubscriptionUrlFactory */
		$factory = $this->get_mp_instance( \MailPoet\Subscription\SubscriptionUrlFactory::class );
		return $factory->getConfirmationUrl( $subscriber );
	}

	public function filter_from( string $from ): string {
		if ( !is_email( $from ) ) {
			return 'events@transgression.party';
		}
		return $from;
	}

	/** ADMIN STUFF */

	public function action_admin_init() {
		if ( !$this->admin_email_page ) {
			return;
		}

		add_settings_section( self::SETTING_SECTION, '', '', $this->admin_email_page );
		foreach ( self::TEMPLATES as $option_key => $option_label ) {
			register_setting( self::SETTING_GROUP, $option_key, [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
			add_settings_field(
				$option_key,
				$option_label,
				[$this, 'render_template_picker'],
				$this->admin_email_page,
				self::SETTING_SECTION,
				[ 'label_for' => $option_key, 'name' => $option_key ]
			);
		}
	}

	public function action_admin_menu() {
		$this->admin_email_page = add_submenu_page(
			self::ADMIN_PAGE,
			'Emails',
			'Emails',
			'manage_options',
			self::EMAIL_PAGE,
			[$this, 'render_email_menu']
		);
	}

	protected function do_test_email( string $template_key ) {
		check_admin_referer( 'test-email-' . $template_key );
		if ( !isset( self::TEMPLATES[$template_key] ) ) {
			add_settings_error( self::SETTING_ERRORS, 'invalid-template', 'Could not find template' );
			return;
		}

		$user_id = get_current_user_id();
		$result = $this->send_user_email( $user_id, $template_key );
		if ( is_wp_error( $result ) ) {
			foreach ( $result->get_error_codes() as $error_code ) {
				add_settings_error( self::SETTING_ERRORS, $error_code, $result->get_error_message( $error_code ) );
			}
		} else {
			$user = get_userdata( $user_id );
			add_settings_error(
				self::SETTING_ERRORS,
				'email-sent', "Email sent to {$user->user_email}!", 'success' );
		}
	}

	public function render_email_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( self::SETTING_ERRORS, 'saved', 'Settings Saved', 'updated' );
		} else if ( isset( $_GET['test-email'] ) ) {
			$this->do_test_email( sanitize_title_with_dashes( $_GET['test-email'] ) );
		}

		settings_errors( self::SETTING_ERRORS );
		printf(
			'<div class="wrap"><h1>%s</h1><form action="options.php" method="post">',
			esc_html( get_admin_page_title() )
		);
		settings_fields( self::SETTING_GROUP );
		do_settings_sections( $this->admin_email_page );
		submit_button( 'Save Settings' );
		echo '</form></div>';
	}

	public function render_template_picker( array $args ) {
		if ( !$this->newsletter_repo ) {
			echo 'MailPoet not loaded';
			return;
		}
		printf(
			'<select name="%s" id="%s">',
			esc_attr( $args['name'] ),
			esc_attr( $args['label_for'] )
		);
		echo '<option value="0">None</option>';
		$newsletters = $this->newsletter_repo->findDraftByTypes( [\MailPoet\Entities\NewsletterEntity::TYPE_STANDARD] );
		$current_setting = get_option( $args['name'], 0 );
		foreach ( $newsletters as $newsletter ) {
			printf(
				'<option value="%1$s" %3$s>%2$s</option>',
				esc_attr( $newsletter->getId() ),
				esc_html( $newsletter->getSubject() ),
				selected( $newsletter->getId(), $current_setting, false )
			);
		}
		echo '</select>&nbsp;';
		$test_url = add_query_arg( [
			'page' => self::EMAIL_PAGE,
			'test-email' => $args['name'],
			'_wpnonce' => wp_create_nonce( 'test-email-' . $args['name'] ),
		], admin_url( self::ADMIN_PAGE ) );
		printf(
			'<a class="button button-secondary" href="%s" id="%s-test">Send test</a>',
			esc_url( $test_url ),
			esc_attr( $args['name'] )
		);
	}

	/** HELPERS */

	// phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType
	private function get_mp_instance( string $class ) {
		return \MailPoet\DI\ContainerWrapper::getInstance()->get( $class );
	}
}
