<?php declare( strict_types=1 );

namespace Transgression;

class Applications extends Singleton {
	const POST_TYPE = 'application';

	private $labels = [
		'name' => 'Applications',
		'singular_name' => 'Application',
	];

	public function init() {
		register_post_type( self::POST_TYPE, [
			'label' => $this->labels['name'],
			'labels' => $this->labels,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			'supports' => ['title', 'comments', 'thumbnail'],
			'register_meta_box_cb' => [$this, 'meta_boxes'],
			'delete_with_user' => true,
		] );
	}

	public function meta_boxes() {
	}
}
