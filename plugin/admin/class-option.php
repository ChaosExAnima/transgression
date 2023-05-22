<?php declare( strict_types=1 );

namespace Transgression\Admin;

use const Transgression\PLUGIN_SLUG;

class Option {
	protected mixed $sanitize_cb = null;
	protected mixed $render_cb = null;
	protected array $render_args = [];
	protected string $description = '';
	protected ?string $section = null;

	protected const KSES_TAGS = [
		'strong' => [],
		'em' => [],
		'a' => [
			'href' => true,
			'target' => true,
		],
		'code' => [],
	];

	/**
	 * Creates an admin option
	 *
	 * @param string $key The option key
	 * @param string $label The label
	 * @param mixed $default_value Default value.
	 */
	public function __construct(
		public string $key,
		public string $label,
		public mixed $default_value = null
	) {}

	public function __toString(): string {
		return $this->key;
	}

	/**
	 * Sets the type of option for validation
	 *
	 * @param string|null $type
	 * @return self
	 */
	public function of_type( ?string $type = null ): self {
		$this->render_cb = [ $this, 'render_text_field' ];
		switch ( $type ) {
			case 'bool':
			case 'toggle':
				$this->sanitize_cb = 'boolval';
				$this->render_args = [ 'type' => 'checkbox' ];
				break;
			case 'url':
				$this->sanitize_cb = 'esc_url';
				$this->render_args = [ 'type' => 'url' ];
				break;
			case 'num':
				$this->sanitize_cb = 'floatval';
				// no break
			case 'absint':
				$this->sanitize_cb = 'absint';
				// no break
			case 'int':
				if ( ! isset( $this->sanitize_cb ) ) {
					$this->sanitize_cb = 'intval';
				}
				$this->render_args = [ 'type' => 'number' ];
				break;
			case 'password':
				$this->render_args = [ 'type' => 'password' ];
				break;
			case 'text':
			default:
				$this->sanitize_cb = 'sanitize_text_field';
			}
		return $this;
	}

	public function describe( string $description ): self {
		$this->description = $description;
		return $this;
	}

	public function on_page( Page_Options $page ): self {
		$page->add_setting( $this );
		return $this;
	}

	public function in_section( string $section ): self {
		$this->section = $section;
		return $this;
	}

	public function get(): mixed {
		return get_option( $this->key, $this->default_value );
	}

	public function set( mixed $value ): bool {
		return update_option( $this->key, $value );
	}

	public function render_before( callable $callback ): self {
		add_action( PLUGIN_SLUG . "_option_{$this->key}_before_render", $callback );
		return $this;
	}

	public function render_after( callable $callback ): self {
		add_action( PLUGIN_SLUG . "_option_{$this->key}_after_render", $callback );
		return $this;
	}

	/**
	 * Registers the setting
	 *
	 * @param string $page
	 * @param string $group
	 * @param string $section
	 * @return void
	 */
	public function register( string $page, string $group, string $section ) {
		$register_args = [];
		if ( isset( $this->sanitize_cb ) ) {
			$register_args = [ 'sanitize_callback' => $this->sanitize_cb ];
		}
		register_setting( $group, $this->key, $register_args );

		if ( ! $this->render_cb ) {
			$this->of_type();
		}
		// Use set section if provided.
		if ( $this->section ) {
			$section = $this->section;
		}
		add_settings_field(
			$this->key,
			$this->label,
			[ $this, 'render' ],
			$page,
			$section,
			[
				'label_for' => $this->key,
				'option' => $this,
			]
		);
		do_action( PLUGIN_SLUG . "_option_{$this->key}_after_register", $this, $page, $group, $section );
	}

	public function render(): void {
		do_action( PLUGIN_SLUG . "_option_{$this->key}_before_render", $this );
		call_user_func( $this->render_cb );
		do_action( PLUGIN_SLUG . "_option_{$this->key}_after_render", $this );
		$this->render_description();
	}

	public function render_text_field() {
		$type = $this->render_args['type'] ?? 'text';
		printf(
			'<input id="%1$s" class="regular-text" type="%3$s" name="%1$s" value="%2$s" />',
			esc_attr( $this->key ),
			esc_attr( $this->get() ),
			esc_attr( $type )
		);
	}

	protected function render_description(): void {
		if ( ! $this->description ) {
			return;
		}
		printf(
			'<p class="description">%s</p>',
			wp_kses( $this->description, self::KSES_TAGS ),
		);
	}
}
