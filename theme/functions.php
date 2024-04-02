<?php declare( strict_types=1 );

namespace TransgressionTheme;

function cb( string $func ): callable {
	return __NAMESPACE__ . '\\' . $func;
}

function init() {
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor.css' );
}
add_action( 'init', cb( 'init' ) );

function styles() {
	$theme = wp_get_theme();
	$version = $theme->exists() ? $theme->get( 'Version' ) : 'unknown';
	wp_enqueue_style( 'transgression-styles', get_theme_file_uri( 'style.css' ), [], $version, 'screen' );
}
add_action( 'wp_enqueue_scripts', cb( 'styles' ) );

function redirect() {
	if ( is_author() ) {
		wp_safe_redirect( home_url(), 301 );
		exit;
	}
}
add_action( 'template_redirect', cb( 'redirect' ) );

// Disable Jetpack Blaze
add_filter( 'jetpack_blaze_enabled', '__return_false' );
