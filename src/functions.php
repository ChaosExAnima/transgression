<?php declare( strict_types=1 );

namespace Transgression;

// Includes
require_once 'inc/class-abstract-singleton.php';
require_once 'inc/class-applications.php';
require_once 'inc/class-emails.php';

if ( defined( 'JET_FORM_BUILDER_VERSION' ) && version_compare( JET_FORM_BUILDER_VERSION, '2.0.6', '>=' ) ) {
	require_once 'inc/jetforms.php';
}

function theme_path( string $file = '' ): string {
	$path = untrailingslashit( get_stylesheet_directory() );
	if ( $file && file_exists( "{$path}/${file}" ) ) {
		$path .= "/{$file}";
	}
	return $path;
}

function init() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );

	Applications::instance()->init();
	Emails::instance()->init();
}
add_action( 'init', cb( 'init' ) );

function styles() {
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ) , [], null, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function log_error( \WP_Error $error ) {
	error_log( implode( '; ', $error->get_error_messages() ) );
}

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}
