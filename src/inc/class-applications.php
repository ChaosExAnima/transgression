<?php declare( strict_types=1 );

namespace Transgression;

class Applications {
	private static ?Applications $instance = null;

	const POST_TYPE = 'application';
	const SETTING_GROUP = 'trans_app';
	const ADMIN_PAGE = 'edit.php?post_type=' . self::POST_TYPE;

	private $labels = [
		'name' => 'Applications',
		'singular_name' => 'Application',
	];

	private string $admin_email_page;

	public static function instance(): Applications {
		if ( static::$instance === null ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [$this, 'action_admin_menu'] );
	}

	public function init() {
		register_post_type( self::POST_TYPE, [
			'label' => $this->labels['name'],
			'labels' => $this->labels,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			'supports' => ['title', 'comments', 'thumbnail'],
			'register_meta_box_cb' => [$this, 'meta_boxes'],
			'delete_with_user' => true,
		] );
	}

	public function action_admin_init() {
		if ( !$this->admin_email_page ) {
			return;
		}
		$template_options = [
			'email_approved' => 'Approved Template',
			'email_denied' => 'Denied Template',
			'email_duplicate' => 'Duplicate Application Template',
		];
		$email_section = self::SETTING_GROUP . '_emails';
		add_settings_section( $email_section, '', '', $this->admin_email_page );
		foreach ( $template_options as $option_key => $option_label ) {
			register_setting( self::SETTING_GROUP, $option_key, [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
			add_settings_field(
				$option_key,
				$option_label,
				[$this, 'render_template_picker'],
				$this->admin_email_page,
				$email_section,
				[ 'label_for' => $option_key, 'name' => $option_key ]
			);
		}
	}

	public function meta_boxes() {
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

	private function __clone() {}

	public function __wakeup() {
		throw new \Exception( "Cannot unserialize singleton" );
	}
}
