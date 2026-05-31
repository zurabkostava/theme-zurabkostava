<?php
/**
 * functions.php — Zurab Kostava theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

function zk_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'style', 'script', 'navigation-widgets' ) );
    add_theme_support( 'automatic-feed-links' );
}
add_action( 'after_setup_theme', 'zk_setup' );

function zk_resource_hints( $hints, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
    }
    return $hints;
}
add_filter( 'wp_resource_hints', 'zk_resource_hints', 10, 2 );

function zk_assets() {
    wp_enqueue_style( 'zk-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), null );
    wp_enqueue_style( 'zk-style', get_stylesheet_uri(), array( 'zk-fonts' ), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'zk_assets' );

function zk_register_menus() {
    register_nav_menu('primary-menu', 'Primary Header Menu');
}
add_action('init', 'zk_register_menus');

/**
 * SPA route map — DYNAMIC VERSION.
 * იღებს WordPress-ში შექმნილ ყველა გვერდს ავტომატურად.
 */
function zk_routes() {
    $routes = array(
        '/' => array( 'slug' => null, 'label' => 'Home', 'eyebrow' => '' ),
    );

    $pages = get_pages( array( 'post_status' => 'publish' ) );
    foreach ( $pages as $page ) {
        $path = '/' . get_page_uri( $page );
        $routes[ $path ] = array(
            'slug'    => $page->post_name,
            'label'   => $page->post_title,
            'eyebrow' => get_post_meta( $page->ID, 'zk_eyebrow', true ) ?: 'Z.K — ' . $page->post_title,
        );
    }
    return $routes;
}

/**
 * SPA Menu Walker.
 * გარდაქმნის WP-ის სტანდარტულ მენიუს შენს Custom SPA HTML სტრუქტურად.
 */
class ZK_SPA_Walker extends Walker_Nav_Menu {
    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<ul class="dropdown-menu">';
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes = empty( $item->classes ) ? array() : (array) $item->classes;
        $has_children = in_array( 'menu-item-has-children', $classes );

        $li_classes = array();
        if ( $depth === 0 ) {
            $li_classes[] = 'nav-item';
            if ( $has_children ) $li_classes[] = 'has-dropdown';
        }

        $class_names = join( ' ', array_filter( $li_classes ) );
        $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

        $output .= '<li' . $class_names . '>';

        $url = ! empty( $item->url ) ? $item->url : '';
        $parsed = wp_parse_url( $url );
        $path = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '/';
        if ( empty( $path ) ) $path = '/';

        $link_class = ( $depth === 0 ) ? 'nav-link' : 'dropdown-link';
        $is_current = in_array( 'current-menu-item', $classes ) || in_array( 'current-page-item', $classes );
        if ( $is_current ) $link_class .= ' is-current';

        // თუ Dropdown-ის მთავარი ღილაკია (მაგ: Visual ან Blogs)
        if ( $has_children && $depth === 0 ) {
            $link_class .= ' dropdown-trigger';
            $output .= '<button class="' . esc_attr( $link_class ) . '" type="button" aria-haspopup="true" aria-expanded="false">';
            $output .= esc_html( $item->title );
            $output .= '<svg class="dropdown-caret" width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $output .= '</button>';
        } else {
            // ჩვეულებრივი გვერდები
            $aria = $is_current ? ' aria-current="page"' : '';
            $output .= '<a class="' . esc_attr( $link_class ) . '" data-route="' . esc_attr( $path ) . '" href="' . esc_url( $url ) . '"' . $aria . '>';
            $output .= esc_html( $item->title );
            $output .= '</a>';
        }
    }
}

/**
 * Custom Badass Grid for 3Decade Posts
 * Usage: [3decade_grid] in any page
 */
function zk_custom_post_grid() {
    // მოგვაქვს პოსტები ამ ორი კატეგორიიდან
    $args = array(
        'category_name'  => 'nocturne,aubade', // მძიმე ნიშნავს "OR" (ან ერთია, ან მეორე)
        'posts_per_page' => -1, // -1 ნიშნავს გამოიტანოს ყველა
        'post_status'    => 'publish'
    );

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">No posts found for 3Decade.</p>';
    }

    // ვიწყებთ HTML გრიდის გენერაციას
    $output = '<div class="zk-post-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();

        // ვიღებთ პოსტის მონაცემებს
        $title = get_the_title();
        $link = get_permalink();
        $path = wp_parse_url( $link, PHP_URL_PATH ); // მხოლოდ სუფთა მარშრუტი (SPA-სთვის)

        // კატეგორიის სახელის ამოღება, რომ გრიდში ლამაზად გამოვაჩინოთ
        $categories = get_the_category();
        $cat_name = ! empty( $categories ) ? esc_html( $categories[0]->name ) : 'Post';

        // Thumbnail (თუ აქვს)
        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : '';
        $bg_style = $img_url ? 'style="background-image: url(' . esc_url( $img_url ) . ');"' : '';

        // ვქმნით თითოეულ ქარდს (Card)
        $output .= '<a href="' . esc_url( $link ) . '" class="zk-grid-card" data-route="' . esc_attr( $path ) . '">';
        $output .= '<div class="zk-card-image" ' . $bg_style . '></div>';
        $output .= '<div class="zk-card-content">';
        $output .= '<span class="zk-card-category">' . $cat_name . '</span>';
        $output .= '<h3 class="zk-card-title">' . $title . '</h3>';
        $output .= '</div>';
        $output .= '</a>';
    }

    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}
add_shortcode( '3decade_grid', 'zk_custom_post_grid' );