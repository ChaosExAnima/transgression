<?php declare( strict_types=1 );

namespace Transgression\Helpers;

class Admin {
	protected const SETTING_PREFIX = 'transgression_';

	/** @var callable[] */
	protected array $actions = [];

	/**
	 * Creates admin page
	 *
	 * @param string $setting_group Main group name
	 * @param Admin_Option[] $admin_page_options Array of admin options
	 * @param string $permission Access permission
	 */
	public function __construct(
		public string $setting_group,
		public array $admin_page_options = [],
		protected string $permission = 'manage_options'
	) {
		add_action( 'admin_init', [$this, 'action_admin_init'] );
	}

	public function as_page(
		string $page,
		string $label,
		string $icon,
		?string $menu_label = null
	): Admin {
		$admin_page = add_menu_page(
			$page,
			$label,
			$menu_label ?? $label,
			$this->permission,
			self::SETTING_PREFIX . $page,
			[$this, 'render_page'],
			$icon
		);
		if ( $admin_page ) {
			$this->admin_page_hook = $admin_page;
		}
		return $this;
	}

	public function as_subpage(
		string $parent,
		string $page,
		string $label,
		?string $menu_label = null
	): Admin {
		$admin_page = add_submenu_page(
			$parent,
			$label,
			$menu_label ?? $label,
			$this->permission,
			self::SETTING_PREFIX . $page,
			[$this, 'render_page']
		);
		if ( $admin_page ) {
			$this->admin_page_hook = $admin_page;
		}
		return $this;
	}

	public function add_setting( Admin_Option $option ): Admin {
		$this->page_options[] = $option;
		return $this;
	}

	public function add_action( string $key, callable $callback ): Admin {
		$this->actions[$key] = $callback;
		return $this;
	}

	public function add_message( string $message, string $type ) {
		add_settings_error(
			"{$this->setting_group}_messages",
			sanitize_title( substr( $message, 0, 10 ) ),
			$message,
			$type
		);
	}

	public function action_admin_init() {
		if ( !$this->admin_page_hook ) {
			return;
		}

		$section = "{$this->setting_group}_section";
		add_settings_section( $section, '', '', $this->admin_page_hook );
		foreach ( $this->page_options as $admin_option ) {
			$admin_option->register(
				$this->admin_page_hook,
				$this->setting_group,
				$section
			);
		}
	}

	public function render_page() {
		if ( ! current_user_can( $this->permission ) ) {
			wp_die();
		}

		if ( !isset( $this->actions['save_message'] ) ) {
			$this->save_message();
		}

		foreach ( $this->actions as $action ) {
			call_user_func( $action ); // phpcs:ignore
		}

		settings_errors( "{$this->setting_group}_messages" );
		printf(
			'<div class="wrap"><h1>%s</h1><form action="options.php" method="post">',
			esc_html( get_admin_page_title() )
		);
		settings_fields( $this->setting_group );
		do_settings_sections( $this->admin_page_hook );
		submit_button( 'Save Settings' );
		echo '</form></div>';
	}

	protected function save_message() {
		if ( isset( $_GET['settings-updated'] ) ) {
			$this->add_message( 'Settings Saved', 'updated' );
		}
	}
}
