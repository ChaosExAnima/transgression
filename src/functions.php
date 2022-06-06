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

function log_error( \Throwable|\WP_Error $error ) {
	if ( $error instanceof \Throwable ) {
		error_log( $error->__toString() );
	} else if ( $error instanceof \WP_Error ) {
		foreach ( $error->get_error_codes() as $error_code ) {
			$message = $error->get_error_message( $error_code );
			error_log( "{$error_code}: {$message}" );
		}
	}
}

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}
