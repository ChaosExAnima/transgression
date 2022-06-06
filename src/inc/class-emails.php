<?php declare( strict_types=1 );

namespace Transgression;

use WP_Error;

class Emails extends Singleton {
	const ADMIN_PAGE = 'edit.php?post_type=' . Applications::POST_TYPE;
	const SETTING_GROUP = 'transgression_emails';
	const SETTING_SECTION = self::SETTING_GROUP . '_emails';
	const TEMPLATES = [
		'email_approved' => 'Approved Template',
		'email_denied' => 'Denied Template',
		'email_duplicate' => 'Duplicate Application Template',
	];

	private string $admin_email_page = '';

	public function init() {
		add_action( 'admin_menu', [$this, 'action_admin_menu'] );
		add_action( 'admin_init', [$this, 'action_admin_init'] );
	}

	public function send_template() {

	}

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
			'apps_emails',
			[$this, 'render_email_menu']
		);
	}

	public function render_email_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'apps_messages', 'apps_saved', 'Settings Saved', 'updated' );
		}

		settings_errors( 'apps_messages' );
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
		if ( !function_exists( __NAMESPACE__.'\\get_newsletters' ) ) {
			echo 'MailPoet not loaded';
			return;
		}
		printf(
			'<select name="%s" id="%s">',
			esc_attr( $args['name'] ),
			esc_attr( $args['label_for'] )
		);
		echo '<option value="0">None</option>';
		$newsletters = get_newsletters();
		$current_setting = get_option( $args['name'], 0 );
		foreach ( $newsletters as $newsletter ) {
			printf(
				'<option value="%1$s" %3$s>%2$s</option>',
				esc_attr( $newsletter->getId() ),
				esc_html( $newsletter->getSubject() ),
				selected( $newsletter->getId(), $current_setting, false )
			);
		}
		echo '</select>';
	}
}

function send_email( string $email, string $subject, string $template, array $params = [] ): ?WP_Error {
	ob_start();
	include theme_path( "/inc/emails/{$template}.php" );
	$body = ob_get_clean();
	$success = _send( $email, prefix_subject( $subject ), $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	if ( !$success ) {
		return new WP_Error( 'email-failed', 'Could not send email', compact( 'email', 'subject', 'template', 'params' ) );
	}
	return null;
}

function send_user_email( int $user_id, string $subject, string $template, array $params = [] ): ?WP_Error {
	$user = get_userdata( $user_id );
	if ( !$user ) {
		return new WP_Error( 'email-no-user', 'User not found', compact( 'user_id' ) );
	}
	send_email( $user->user_email, $subject, $template, array_merge( $params, compact( 'user' ) ) );
	return null;
}

function prefix_subject( string $subject ): string {
	return get_bloginfo() . ": {$subject}";
}

function filter_from( string $from ): string {
	if ( !is_email( $from ) ) {
		return 'events@transgression.party';
	}
	return $from;
}
add_filter( 'wp_mail_from', cb( 'filter_from' ) );

function _send( string $email, string $subject, string $body, array $headers = [] ): bool {
	return wp_mail( $email, $subject, $body, $headers );
}
