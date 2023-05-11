<?php declare( strict_types=1 );

namespace Transgression\Admin;

use WP_Screen;

use const Transgression\PLUGIN_SLUG;

class Page {
	protected const SETTING_PREFIX = PLUGIN_SLUG . '_';

	protected string $page_slug = '';

	/** @var string[] */
	protected array $actions = [];

	protected string $page_hook = '';

	/** @var string[] */
	protected array $sections = [];

	protected mixed $render_cb = null;

	protected string $description = '';

	/**
	 * Creates admin page
	 *
	 * @param string $setting_group Main group name
	 * @param Option[] $page_options Array of admin options
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
		string $parent_page,
		string $page,
		string $label,
		?string $menu_label = null
	): Page {
		$this->page_slug = self::SETTING_PREFIX . $page;
		$callback = function () use ( $parent_page, $page, $label, $menu_label ) {
			$admin_page = add_submenu_page(
				$parent_page,
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
	 * @return self
	 */
	public function set_render( callable $render ): self {
		$this->render_cb = $render;
		return $this;
	}

	/**
	 * Adds a new setting
	 *
	 * @param Option $option
	 * @return self
	 */
	public function add_setting( Option $option ): self {
		$this->page_options[ $option->key ] = $option;
		return $this;
	}

	/**
	 * Adds multiple settings
	 *
	 * @param Option|string $section_option
	 * @param Option $options
	 * @return self
	 */
	public function add_settings( Option|string $section_option, Option ...$options ): self {
		if ( $section_option instanceof Option ) {
			$options[] = $section_option;
		}
		foreach ( $options as $option ) {
			if ( is_string( $section_option ) ) {
				$option->in_section( $section_option );
			}
			$this->add_setting( $option );
		}
		return $this;
	}

	/**
	 * Gets a setting
	 *
	 * @param string $key
	 * @return Option|null
	 */
	public function get_setting( string $key ): ?Option {
		return $this->page_options[ $key ] ?? null;
	}

	/**
	 * Gets a setting's value
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function value( string $key ): mixed {
		return ( $this->page_options[ $key ] )->get() ?? null;
	}

	/**
	 * Adds a section
	 *
	 * @param string $key
	 * @param string $name
	 * @return self
	 */
	public function add_section( string $key, string $name ): self {
		$this->sections[ $key ] = $name;
		return $this;
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
	 * Redirects to action with given key
	 *
	 * @param string $key
	 * @param string $value
	 * @param array $params Additional parameters to add
	 * @return void
	 */
	public function action_redirect( string $key, string $value, array $params = [] ) {
		if ( ! in_array( $key, $this->actions, true ) ) {
			return;
		}
		$params[ $key ] = $value;
		$url = $this->get_url( $params );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Adds an action on on page load
	 *
	 * @param string $key Action key to check in request object
	 * @param callable $callback Callback to run when set
	 * @return Admin
	 */
	public function add_action( string $key, callable $callback ): self {
		$this->actions[] = $key;
		add_action( self::SETTING_PREFIX . "admin_{$this->setting_group}_action_{$key}", $callback, 10, 2 );
		return $this;
	}

	/**
	 * Adds a message
	 *
	 * @param string $message The message html
	 * @param string $type Type of message. One of notice, error, success, or warning
	 * @return void
	 */
	public function add_message( string $message, string $type = 'error' ) {
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
		if ( count( $this->sections ) === 0 ) {
			add_settings_section( $section, '', '', $this->page_hook );
		} else {
			foreach ( $this->sections as $section_key => $section_name ) {
				add_settings_section( $section_key, $section_name, '', $this->page_hook );
			}
		}
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

		foreach ( $this->actions as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_REQUEST[ $key ] ) ) {
				do_action(
					self::SETTING_PREFIX . "admin_{$this->setting_group}_action_{$key}",
					// phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ),
					$this
				);
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
}
