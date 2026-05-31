<?php
/**
 * functions.php — Zurab Kostava theme
 *
 * Kept intentionally lean. Its main job is to actually LOAD style.css
 * on the frontend (WordPress does not do this automatically — it only
 * reads style.css for the theme header metadata) and to pull in the font.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Theme supports.
 */
function zk_setup() {
	add_theme_support( 'title-tag' );          // Let WP manage <title>.
	add_theme_support( 'post-thumbnails' );     // Featured images.
	add_theme_support( 'html5', array( 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'automatic-feed-links' );
}
add_action( 'after_setup_theme', 'zk_setup' );

/**
 * Faster font loading.
 */
function zk_resource_hints( $hints, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'zk_resource_hints', 10, 2 );

/**
 * Enqueue styles.
 */
function zk_assets() {
	$theme = wp_get_theme();

	// Inter — clean, modern, premium.
	wp_enqueue_style(
		'zk-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
		array(),
		null
	);

	// The theme stylesheet (style.css) — versioned for cache-busting.
	wp_enqueue_style(
		'zk-style',
		get_stylesheet_uri(),
		array( 'zk-fonts' ),
		$theme->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'zk_assets' );
