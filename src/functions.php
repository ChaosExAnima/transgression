<?php declare( strict_types=1 );

namespace Transgression;

function styles() {
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ) , [], null, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function cb( string $func ): Callable {
    return __NAMESPACE__ . '\\' . $func;
}
