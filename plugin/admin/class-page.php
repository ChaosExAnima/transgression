<?php declare( strict_types=1 );

namespace Transgression\Admin;

use WP_Screen;

use const Transgression\PLUGIN_SLUG;

class Page {
	protected const SETTING_PREFIX = PLUGIN_SLUG . '_';

	protected string $page_slug = '';

	/** @var callable[] */
	protected array $actions = [];

	protected string $page_hook = '';

	protected mixed $render_cb = null;

	protected string $description = '';

	/**
	 * Creates admin page
	 *
	 * @param string $setting_group Main group name
	 * @param Admin_Option[] $page_options Array of admin options
	 * @param string $permission Access permission
	 */
	public function __construct(
		public string $setting_group,
		public array $page_options = [],
		protected string $permission = 'manage_options'
	) {
		add_action( 'admin_init', [ $this, 'action_admin_init' ] );
	}

	public function as_page(
		string $page,
		string $label,
		string $icon,
		?string $menu_label = null
	): Page {
		$this->page_slug = self::SETTING_PREFIX . $page;
		$callback = function () use ( $page, $label, $menu_label, $icon ) {
			$admin_page = add_menu_page(
				$label,
				$menu_label ?? $label,
				$this->permission,
				self::SETTING_PREFIX . $page,
				[ $this, 'render_page' ],
				$icon
			);
			if ( $admin_page ) {
				$this->page_hook = $admin_page;
			}
		};
		add_action( 'admin_menu', $callback );
		return $this;
	}

	public function as_subpage(
		string $parent,
		string $page,
		string $label,
		?string $menu_label = null
	): Page {
		$this->page_slug = self::SETTING_PREFIX . $page;
		$callback = function () use ( $parent, $page, $label, $menu_label ) {
			$admin_page = add_submenu_page(
				$parent,
				$label,
				$menu_label ?? $label,
				$this->permission,
				self::SETTING_PREFIX . $page,
				[ $this, 'render_page' ]
			);
			if ( $admin_page ) {
				$this->page_hook = $admin_page;
			}
		};
		add_action( 'admin_menu', $callback );
		return $this;
	}

	public function as_post_subpage(
		string $post_type,
		string $page,
		string $label,
		?string $menu_label = null
	): self {
		return $this->as_subpage(
			"edit.php?post_type={$post_type}",
			$page,
			$label,
			$menu_label
		);
	}

	public function with_description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Sets the render callback
	 *
	 * @param callable $render
	 * @return Admin
	 */
	public function set_render( callable $render ): self {
		$this->render_cb = $render;
		return $this;
	}

	/**
	 * Adds a new setting
	 *
	 * @param Option $option
	 * @return Admin
	 */
	public function add_setting( Option $option ): self {
		$this->page_options[ $option->key ] = $option;
		return $this;
	}

	/**
	 * Gets a setting
	 *
	 * @param string $key
	 * @return Admin_Option|null
	 */
	public function get_setting( string $key ): ?Option {
		return $this->page_options[ $key ] ?? null;
	}

	/**
	 * Gets the page URL based off of the page hook
	 *
	 * @param array $params
	 * @return string
	 */
	public function get_url( array $params = [] ): string {
		$screen = WP_Screen::get( $this->page_hook );
		$params['page'] = $this->page_slug;
		return add_query_arg( $params, admin_url( $screen->parent_file ) );
	}

	/**
	 * Adds an action on on page load
	 *
	 * @param string $key Action key to check in request object
	 * @param callable $callback Callback to run when set
	 * @return Admin
	 */
	public function add_action( string $key, callable $callback ): self {
		$this->actions[ $key ] = $callback;
		return $this;
	}

	/**
	 * Adds a message
	 *
	 * @param string $message The message html
	 * @param string $type Type of message. One of notice, error, success, or warning
	 * @return void
	 */
	public function add_message( string $message, string $type ) {
		add_settings_error(
			"{$this->setting_group}_messages",
			sanitize_title( substr( $message, 0, 10 ) ),
			$message,
			$type
		);
	}

	/**
	 * Initializes on admin load
	 *
	 * @return void
	 */
	public function action_admin_init() {
		if ( ! $this->page_hook ) {
			return;
		}

		$section = "{$this->setting_group}_section";
		add_settings_section( $section, '', '', $this->page_hook );
		foreach ( $this->page_options as $admin_option ) {
			$admin_option->register(
				$this->page_hook,
				$this->setting_group,
				$section
			);
		}
	}

	public function render_page() {
		if ( ! current_user_can( $this->permission ) ) {
			wp_die();
		}

		if ( ! isset( $this->actions['save_message'] ) ) {
			$this->save_message();
		}

		foreach ( $this->actions as $key => $action ) {
			if ( isset( $_REQUEST[ $key ] ) ) {
				call_user_func( $action, sanitize_text_field( $_REQUEST[ $key ] ), $this );
			}
		}

		settings_errors( "{$this->setting_group}_messages" );
		printf(
			'<div class="wrap"><h1>%s</h1><form action="options.php" method="post">',
			esc_html( get_admin_page_title() )
		);
		if ( $this->description ) {
			printf(
				'<p>%s</p>',
				wp_kses_post( $this->description )
			);
		}
		settings_fields( $this->setting_group );
		do_settings_sections( $this->page_hook );
		if ( is_callable( $this->render_cb ) ) {
			call_user_func( $this->render_cb, $this );
		}
		submit_button( 'Save Settings' );
		echo '</form></div>';
	}

	protected function save_message() {
		if ( isset( $_GET['settings-updated'] ) ) {
			$this->add_message( 'Settings Saved', 'updated' );
		}
	}
}
