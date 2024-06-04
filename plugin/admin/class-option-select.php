<?php declare( strict_types = 1 );

namespace Transgression\Admin;

class Option_Select extends Option {
	protected array $options = [];

	protected bool $show_none = true;

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
	) {
		$this->sanitize_cb = [ $this, 'sanitize_option' ];
		$this->render_cb = [ $this, 'render_select' ];
		parent::__construct( $key, $label, $default_value );
	}

	public function of_type( ?string $type = null ): static {
		return $this;
	}

	public function without_none(): static {
		$this->show_none = false;
		return $this;
	}

	public function with_options( array $options ): static {
		$this->options = $options;
		return $this;
	}

	public function sanitize_option( mixed $input ): mixed {
		if ( isset( $this->options[ $input ] ) ) {
			return $input;
		}
		return $this->default_value;
	}

	protected function render_select(): void {
		printf(
			'<select name="%1$s" id="%1$s">',
			esc_attr( $this->key )
		);
		if ( $this->show_none ) {
			echo '<option value="0">None</option>';
		}
		$current_value = $this->get();
		foreach ( $this->options as $key => $label ) {
			printf(
				'<option value="%1$s" %3$s>%2$s</option>',
				esc_attr( $key ),
				esc_html( $label ),
				selected( $key, $current_value, false )
			);
		}
		echo '</select>';
	}
}
