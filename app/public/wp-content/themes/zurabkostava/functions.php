<?php
/**
 * functions.php — Zurab Kostava theme
 *
 * Loads the stylesheet + font, and provides the single source of truth for
 * the SPA route map (used by the nav, the server-side render, and the
 * client-side router alike).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Theme supports.
 */
function zk_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
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

	wp_enqueue_style(
		'zk-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'zk-style',
		get_stylesheet_uri(),
		array( 'zk-fonts' ),
		$theme->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'zk_assets' );

/**
 * SPA route map — the single source of truth.
 *
 * route  => the clean URL path (also the page's permalink path)
 * slug   => the WordPress Page slug to pull content from (null = the hero)
 * label  => nav text / <title>
 * eyebrow=> the small kicker shown above the page title
 */
function zk_routes() {
	return array(
		'/'            => array( 'slug' => null,         'label' => 'Home',        'eyebrow' => '' ),
		'/about'       => array( 'slug' => 'about',       'label' => 'About',       'eyebrow' => '01 — About' ),
		'/projects'    => array( 'slug' => 'projects',    'label' => 'Projects',    'eyebrow' => '02 — Projects' ),
		'/music'       => array( 'slug' => 'music',       'label' => 'Music',       'eyebrow' => '03 — Music' ),
		'/books'       => array( 'slug' => 'books',       'label' => 'Books',       'eyebrow' => '04 — Books' ),
		'/gallery'     => array( 'slug' => 'gallery',     'label' => 'Gallery',     'eyebrow' => '05 — Visual · Gallery' ),
		'/photography' => array( 'slug' => 'photography', 'label' => 'Photography', 'eyebrow' => '06 — Visual · Photography' ),
		'/video'       => array( 'slug' => 'video',       'label' => 'Video',       'eyebrow' => '07 — Visual · Video' ),
	);
}

/**
 * Canonical URL for a route. Uses the real Page permalink when the Page
 * exists, so direct links never trigger a trailing-slash redirect.
 */
function zk_route_url( $route, $slug = null ) {
	if ( $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
	}
	return home_url( '/' === $route ? '/' : $route );
}

/**
 * Render a single nav link with the correct href + active state.
 */
function zk_nav_link( $route, $label, $current, $class = 'nav-link' ) {
	$routes = zk_routes();
	$slug   = isset( $routes[ $route ]['slug'] ) ? $routes[ $route ]['slug'] : null;
	$is_cur = ( $route === $current );

	return sprintf(
		'<a class="%1$s%2$s" data-route="%3$s" href="%4$s"%5$s>%6$s</a>',
		esc_attr( $class ),
		$is_cur ? ' is-current' : '',
		esc_attr( $route ),
		esc_url( zk_route_url( $route, $slug ) ),
		$is_cur ? ' aria-current="page"' : '',
		esc_html( $label )
	);
}

function zk_register_menus() {
    register_nav_menu('primary-menu', 'Primary Header Menu');
}
add_action('init', 'zk_register_menus');