<?php declare( strict_types = 1 );

namespace Transgression\Admin;

class Option_Textarea extends Option {
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
		$this->sanitize_cb = 'sanitize_text_field';
		$this->render_cb = [ $this, 'render_text_area' ];
		parent::__construct( $key, $label, $default );
	}

	protected function render_text_area(): void {
		printf(
			'<textarea id="%1$s" class="large-text" name="%1$s">%2$s</textarea>',
			esc_attr( $this->key ),
			esc_textarea( $this->get() )
		);
	}
}
