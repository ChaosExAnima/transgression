<?php declare( strict_types = 1 );

namespace Transgression\Helpers;

class Admin_Select_Option extends Admin_Option {
	protected array $options = [];

	protected bool $show_none = true;

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
	) {
		$this->sanitize_cb = [ $this, 'sanitize_option' ];
		$this->render_cb = [ $this, 'render_select' ];
		parent::__construct( $key, $label, $default );
	}

	public function of_type( ?string $type = null ): Admin_Select_Option {
		return $this;
	}

	public function without_none(): Admin_Select_Option {
		$this->show_none = false;
		return $this;
	}

	public function with_options( array $options ): Admin_Select_Option {
		$this->options = $options;
		return $this;
	}

	public function sanitize_option( mixed $input ): mixed {
		if ( isset( $this->options[ $input ] ) ) {
			return $input;
		}
		return $this->default;
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
