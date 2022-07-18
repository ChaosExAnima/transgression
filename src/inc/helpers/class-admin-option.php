<?php declare( strict_types=1 );

namespace Transgression\Helpers;

class Admin_Option {
	protected mixed $sanitize_cb;
	protected mixed $render_cb;
	protected array $render_args = [];
	protected string $description = '';

	/**
	 * Creates an admin option
	 *
	 * @param string $key The option key
	 * @param string $label The label
	 * @param mixed $default Default value.
	 */
	public function __construct(
		public string $key,
		public string $label,
		public mixed $default = null
	) {}

	public function __toString(): string {
		return $this->key;
	}

	public function of_type( ?string $type = null ): Admin_Option {
		$this->render_cb = [$this, 'render_text_field'];
		switch ( $type ) {
			case 'url':
				$this->sanitize_cb = 'esc_url';
				$this->render_args = ['type' => 'url'];
				break;
			case 'num':
				$this->sanitize_cb = 'floatval';
			case 'absint':
				$this->sanitize_cb = 'absint';
			case 'int':
				if ( !$this->sanitize_cb ) {
					$this->sanitize_cb = 'intval';
				}
				$this->render_args = ['type' => 'number'];
				break;
			case 'text':
			default:
				$this->sanitize_cb = 'sanitize_text_field';
			}
		return $this;
	}

	public function describe( string $description ): Admin_Option {
		$this->description = $description;
		return $this;
	}

	public function on_page( Admin $page ): Admin_Option {
		$page->add_setting( $this );
		return $this;
	}

	public function get(): mixed {
		return get_option( $this->key, $this->default );
	}

	public function set( mixed $value ): bool {
		return update_option( $this->key, $value );
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
		if ( $this->sanitize_cb ) {
			$register_args = ['sanitize_callback' => $this->sanitize_cb];
		}
		register_setting( $group, $this->key, $register_args );

		if ( !$this->render_cb ) {
			$this->of_type();
		}
		add_settings_field(
			$this->key,
			$this->label,
			$this->render_cb,
			$page,
			$section,
			[ 'label_for' => $this->key, 'option' => $this ]
		);
	}

	public function render_text_field() {
		printf(
			'<input id="%1$s" class="regular-text" type="%3$s" name="%1$s" value="%2$s" />',
			esc_attr( $this->key ),
			esc_attr( $this->get() ),
			esc_attr( $this->render_args['type'] ?? 'text' )
		);
		$this->render_description();
	}

	protected function render_description() {
		if ( !$this->description ) {
			return;
		}
		$kses_tags = [
			'strong' => [],
			'em' => [],
			'a' => [
				'href' => true,
				'target' => true,
			],
		];
		printf(
			'<p class="description">%s</p>',
			wp_kses( $this->description, $kses_tags ),
		);
	}
}
