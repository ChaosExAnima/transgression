<?php declare( strict_types=1 );

namespace Transgression\Admin;

use WP_Screen;

use const Transgression\PLUGIN_SLUG;

class Page_Options extends Page {
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
	 * @param string $label Page title
	 * @param string|null $menu_label Menu label
	 * @param Option[] $page_options Array of admin options
	 * @param string $permission Access permission
	 */
	public function __construct(
		public string $setting_group,
		protected string $label,
		?string $menu_label = null,
		public array $page_options = [],
		protected string $permission = 'manage_options'
	) {
		parent::__construct( $setting_group, $label, $menu_label, $permission );
		add_action( 'admin_init', [ $this, 'action_admin_init' ] );
		$this->add_render_callback( [ $this, 'render_options' ] );
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

	public function render_options() {
		printf(
			'<form action="%s" method="post">',
			esc_url( admin_url( 'options.php' ) )
		);
		settings_fields( $this->setting_group );
		do_settings_sections( $this->page_hook );
		submit_button( 'Save Settings' );
		echo '</div>';
	}
}
