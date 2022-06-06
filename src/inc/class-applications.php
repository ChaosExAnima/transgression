<?php declare( strict_types=1 );

namespace Transgression;

class Applications {
	private static ?Applications $instance = null;

	const POST_TYPE = 'application';

	private $labels = [
		'name' => 'Applications',
		'singular_name' => 'Application',
	];

	public static function instance(): Applications {
		if ( static::$instance === null ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {}

	public function init() {
		register_post_type( self::POST_TYPE, [
			'label' => $this->labels['name'],
			'labels' => $this->labels,
			'show_ui' => true,
			'show_in_admin_bar' => false,
			'menu_icon' => 'dashicons-text',
			// 'capability_type' => ['application', 'applications'],
			// 'map_meta_cap' => true,
			'supports' => ['title', 'comments', 'thumbnail'],
			'register_meta_box_cb' => [$this, 'meta_boxes'],
			'delete_with_user' => true,
		] );
	}

	public function meta_boxes() {
	}

	private function __clone() {}

	private function __wakeup() {
		throw new Exception( "Cannot unserialize singleton" );
	}
}
