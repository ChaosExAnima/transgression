<?php declare( strict_types=1 );

namespace Transgression;

use Exception;

/**
 * Enable autoloading of plugin classes in namespace
 * @param string $class_name
 */
function autoload( string $class_name ) {
	// Only autoload classes from this namespace
	if ( false === str_starts_with( $class_name, __NAMESPACE__ ) ) {
		return;
	}

	// Remove namespace from class name
	$class_file = str_replace( __NAMESPACE__ . '\\', '', $class_name );

	// Convert class name format to file name format
	$class_file = strtolower( $class_file );
	$class_file = str_replace( '_', '-', $class_file );

	// Convert sub-namespaces into directories
	$class_path = explode( '\\', $class_file );
	$class_file = array_pop( $class_path );
	$class_path = implode( '/', $class_path );

	// Load the class
	$types = ['abstract', 'trait', 'enum', 'class'];
	foreach ( $types as $type ) {
		$path = __DIR__ . "/inc/{$class_path}/{$type}-{$class_file}.php";
		if ( file_exists(  $path ) ) {
			require_once $path;
			return;
		}
	}
	throw new Exception( "Could not find class ${class_file} at {$path}" );
}

spl_autoload_register( cb( 'autoload' ) );

// Includes
require_once 'inc/helpers.php';
if ( defined( 'JET_FORM_BUILDER_VERSION' ) && version_compare( JET_FORM_BUILDER_VERSION, '2.0.6', '>=' ) ) {
	require_once 'inc/jetforms.php';
}

function init() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );

	add_theme_support( 'woocommerce' );

	Applications::instance()->init();
	Discord::instance()->init();
	Emails::instance()->init();
	People::instance()->init();
	WooCommerce::instance()->init();
}
add_action( 'init', cb( 'init' ) );

function styles() {
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ) , [], null, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function redirect() {
    if ( is_author() ) {
        wp_redirect( home_url(), 301 );
        exit;
    }
}
add_action( 'template_redirect', cb( 'redirect' ) );

function log_error( \Throwable|\WP_Error|string $error ) {
	if ( $error instanceof \Throwable ) {
		error_log( $error->__toString() );
	} else if ( $error instanceof \WP_Error ) {
		foreach ( $error->get_error_codes() as $error_code ) {
			$message = $error->get_error_message( $error_code );
			error_log( "{$error_code}: {$message}" );
		}
	} else if ( is_string( $error ) ) {
		error_log( $error );
	}
}

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}
