<?php declare( strict_types=1 );

namespace TransgressionTheme;

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}

// Includes
require_once 'inc/helpers.php';
require_once 'inc/jetforms.php';
require_once 'inc/woocommerce.php';

function init() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );

	add_theme_support( 'woocommerce' );
	remove_theme_support(  'wc-product-gallery-slider' );
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
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

