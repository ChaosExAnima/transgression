<?php declare( strict_types=1 );

namespace Transgression;

// Includes
require_once 'inc/class-applications.php';

if ( defined( 'JET_FORM_BUILDER_VERSION' ) && version_compare( JET_FORM_BUILDER_VERSION, '2.0.6', '>=' ) ) {
	require_once 'inc/jetforms.php';
}

function init() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );

	Applications::init();
}
add_action( 'init', cb( 'init' ) );

function styles() {
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ) , [], null, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}
