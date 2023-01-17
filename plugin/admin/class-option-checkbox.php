<?php declare( strict_types = 1 );

namespace Transgression\Admin;

class Option_Checkbox extends Option {
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
		$this->sanitize_cb = 'intval';
		$this->render_cb = [ $this, 'render_checkbox' ];
		parent::__construct( $key, $label, $default );
	}

	public function get(): mixed {
		return boolval( parent::get() );
	}

	protected function render_checkbox(): void {
		if ( $this->description ) {
			printf( '<label for="%s">', esc_attr( $this->key ) );
		}
		printf(
			'<input id="%1$s" class="regular-text" type="checkbox" name="%1$s" value="1" %3$s /> ',
			esc_attr( $this->key ),
			esc_attr( $this->get() ),
			checked( true, $this->get(), false )
		);
		if ( $this->description ) {
			echo wp_kses( $this->description, self::KSES_TAGS );
			echo '</label>';
		}
	}

	protected function render_description(): void {}
}
