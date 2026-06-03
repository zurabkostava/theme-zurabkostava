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

    // CSS-ის მიბმა
    wp_enqueue_style( 'zk-style', get_stylesheet_uri(), array( 'zk-fonts' ), filemtime( get_stylesheet_directory() . '/style.css' ) );

    // ახალი app.js ფაილის მიბმა სუფთად
    wp_enqueue_script( 'zk-app', get_stylesheet_directory_uri() . '/app.js', array(), filemtime( get_stylesheet_directory() . '/app.js' ), true );

    // აქ ვაწვდით app.js-ს ვორდპრესის დინამიურ ლინკებს (ამის წყალობით index.php-დან window.ZK სკრიპტის წაშლაც შეგვიძლია)
    wp_localize_script( 'zk-app', 'ZK', array(
        'home' => home_url( '/' ),
        'site' => get_bloginfo( 'name' ),
    ) );
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
/**
 * SPA Menu Walker - Supports Nested Dropdowns
 */
class ZK_SPA_Walker extends Walker_Nav_Menu {
    public function start_lvl( &$output, $depth = 0, $args = null ) {
        // თუ მეორე დონეა (ან უფრო ღრმა), ვამატებთ nested-menu კლასს
        $class = $depth >= 1 ? 'dropdown-menu nested-menu' : 'dropdown-menu';
        $output .= '<ul class="' . esc_attr( $class ) . '">';
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes = empty( $item->classes ) ? array() : (array) $item->classes;
        $has_children = in_array( 'menu-item-has-children', $classes );

        $li_classes = array();
        if ( $depth === 0 ) {
            $li_classes[] = 'nav-item';
            if ( $has_children ) $li_classes[] = 'has-dropdown';
        } else {
            // მეორადი ჩაშლის კლასები
            $li_classes[] = 'nested-item';
            if ( $has_children ) $li_classes[] = 'has-nested-dropdown';
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

        // ლინკი ჩაშლისთვის (გახდა კლიკებადი)
        if ( $has_children ) {
            $link_class .= ' dropdown-trigger';
            $aria = $is_current ? ' aria-current="page"' : '';
            $output .= '<a class="' . esc_attr( $link_class ) . '" data-route="' . esc_attr( $path ) . '" href="' . esc_url( $url ) . '" aria-haspopup="true" aria-expanded="false"' . $aria . '>';
            $output .= esc_html( $item->title );

            // მეორად ჩაშლას ოდნავ სხვა ისარი სჭირდება (მარჯვნივ მიმართული)
            $caret_class = $depth >= 1 ? 'dropdown-caret nested-caret' : 'dropdown-caret';
            $output .= '<svg class="' . esc_attr( $caret_class ) . '" width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $output .= '</a>';
        } else {
            // ჩვეულებრივი ლინკი
            $aria = $is_current ? ' aria-current="page"' : '';

            // ვამოწმებთ, არის თუ არა ლინკი გარე საიტის (არ შეიცავს შენი საიტის მთავარ მისამართს)
            $is_external = ( strpos( $url, home_url() ) === false && ( strpos( $url, 'http' ) === 0 || strpos( $url, '//' ) === 0 ) );
            $target = $is_external ? ' target="_blank" rel="noopener noreferrer"' : '';

            $output .= '<a class="' . esc_attr( $link_class ) . '" data-route="' . esc_attr( $path ) . '" href="' . esc_url( $url ) . '"' . $aria . $target . '>';
            $output .= esc_html( $item->title );

            // თუ გარე ლინკია, ავტომატურად ვამატებთ მინიმალისტურ ისრის იკონს
            if ( $is_external ) {
                $output .= '<svg class="zk-external-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>';
            }

            $output .= '</a>';
        }
    }
}

/**
 * Smart Custom Grid for Posts (Updated with Sorting UI and Time Data)
 */
function zk_custom_post_grid( $atts ) {
    $atts = shortcode_atts( array(
        'category' => '',
    ), $atts, 'custom_grid' );

    $args = array(
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    );

    if ( ! empty( $atts['category'] ) ) {
        $args['category_name'] = sanitize_text_field( $atts['category'] );
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">No posts found in this category.</p>';
    }

    // მთავარი კონტეინერი (Wrapper)
    $output = '<div class="zk-grid-wrapper">';

// ფილტრაციის კონტროლები (მყისიერი ძებნა + სორტირება)
    $output .= '<div class="zk-grid-controls">';

    // პრემიუმ შიდა ძებნის ინპუტი (Glass Design)
    $output .= '<div class="zk-search-box">';
    $output .= '<input type="text" class="zk-search-input" placeholder="Search projects..." aria-label="Search">';
    $output .= '<svg class="zk-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    $output .= '</div>';

    // სორტირების Custom Dropdown
    $output .= '<div class="zk-sort-dropdown" id="sortDropdown">';
    $output .= '<button class="zk-sort-trigger" type="button" aria-expanded="false">';
    $output .= '<span class="zk-sort-label">Sort by: </span><span class="zk-sort-current">Newest</span>';
    $output .= '<svg class="dropdown-caret" width="10" height="10" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $output .= '</button>';
    $output .= '<div class="zk-sort-menu">';
    $output .= '<button class="zk-sort-option is-selected" type="button" data-sort="desc">Newest</button>';
    $output .= '<button class="zk-sort-option" type="button" data-sort="asc">Oldest</button>';
    $output .= '</div>'; // end menu
    $output .= '</div>'; // end dropdown
    $output .= '</div>'; // end controls

    // უშუალოდ გრიდი
    $output .= '<div class="zk-post-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();

        $title = get_the_title();
        $link = get_permalink();
        $path = wp_parse_url( $link, PHP_URL_PATH );

        $categories = get_the_category();
        $cat_name = ! empty( $categories ) ? esc_html( $categories[0]->name ) : 'Post';

        $date = get_the_date( 'M j, Y' );
        // ვიღებთ პოსტის გამოქვეყნების ზუსტ წამებს, რათა JS-მა სორტირება შეძლოს
        $timestamp = get_the_time( 'U' );

        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : '';
        $bg_style = $img_url ? 'style="background-image: url(' . esc_url( $img_url ) . ');"' : '';

        // ქარდს ვუმატებთ data-time ატრიბუტს
        $output .= '<a href="' . esc_url( $link ) . '" class="zk-grid-card" data-route="' . esc_attr( $path ) . '" data-time="' . esc_attr( $timestamp ) . '">';
        $output .= '<div class="zk-card-image" ' . $bg_style . '></div>';
        $output .= '<div class="zk-card-content">';

        $output .= '<div class="zk-card-meta">';
        $output .= '<span class="zk-card-category">' . $cat_name . '</span>';
        $output .= '<span class="zk-card-meta-separator"></span>';
        $output .= '<span class="zk-card-date">' . esc_html( $date ) . '</span>';
        $output .= '</div>';

        $output .= '<h3 class="zk-card-title">' . $title . '</h3>';
        $output .= '</div>';
        $output .= '</a>';
    }

    wp_reset_postdata();
    $output .= '</div>'; // იხურება zk-post-grid
    $output .= '</div>'; // იხურება zk-grid-wrapper

    return $output;
}
add_shortcode( 'custom_grid', 'zk_custom_post_grid' );


/* ============================================================
   WP HEAD & FOOTER CLEANUP (მაქსიმალური მინიმალიზმი)
   ============================================================ */
function zk_clean_wp_head() {
    // 1. ემოჯების ამოშლა
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    // 2. RSS Feeds-ის ამოშლა
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    remove_action( 'wp_head', 'feed_links', 2 );

    // 3. oEmbed და REST API ლინკების ამოშლა (თუ ვინმე არ აემბედებს შენს საიტს)
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

    // 4. Shortlink-ისა და WP Generator-ის (ვერსიის) ამოშლა
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
    remove_action( 'wp_head', 'wp_generator' );
    // 6. Global Styles-ის და SVG ფილტრების სრული განადგურება (ახალი WP ვერსიებისთვის)
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}
add_action( 'init', 'zk_clean_wp_head' );

// 5. Gutenberg-ის სტილების, Global Styles-ის და Classic Theme CSS-ის ამოშლა
function zk_remove_wp_block_library_css() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
    wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'zk_remove_wp_block_library_css', 100 );

/* ============================================================
   BREADCRUMBS (ნავიგაციის ბილიკი) - SPA & Page Hierarchy თავსებადი
   ============================================================ */
function zk_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }

    echo '<nav class="zk-breadcrumbs" aria-label="Breadcrumb">';

    // 1. Home Link
    echo '<a href="' . esc_url( home_url( '/' ) ) . '" data-route="/">Home</a>';

    if ( is_single() ) {
        // --- ლოგიკა ცალკეული პოსტებისთვის (მაგ: Nocturne #50) ---

        // ხელით ვამატებთ Blog-ს, რადგან ყველა პოსტი ბლოგის ქვეშაა
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( home_url( '/blog/' ) ) . '" data-route="/blog">Blog</a>';

        $categories = get_the_category();
        if ( ! empty( $categories ) ) {
            $cat = $categories[0];

            // თუ კატეგორიას აქვს მშობელი (მაგ: Raw)
            if ( $cat->parent != 0 ) {
                $parent_cat = get_category( $cat->parent );
                $parent_slug = $parent_cat->slug;
                $parent_path = '/blog/' . $parent_slug;
                echo '<span class="zk-breadcrumb-separator">/</span>';
                echo '<a href="' . esc_url( home_url( $parent_path . '/' ) ) . '" data-route="' . esc_attr( $parent_path ) . '">' . esc_html( $parent_cat->name ) . '</a>';
            }

            // უშუალოდ მიმდინარე კატეგორია (მაგ: Nocturnes)
            $parent_prefix = ( $cat->parent != 0 ) ? '/blog/' . get_category( $cat->parent )->slug . '/' : '/blog/';
            $cat_path = $parent_prefix . $cat->slug;

            echo '<span class="zk-breadcrumb-separator">/</span>';
            echo '<a href="' . esc_url( home_url( $cat_path . '/' ) ) . '" data-route="' . esc_attr( $cat_path ) . '">' . esc_html( $cat->name ) . '</a>';
        }

        // უშუალოდ პოსტის სათაური
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . get_the_title() . '</span>';

    } elseif ( is_page() ) {
        // --- ლოგიკა უშუალოდ გვერდებისთვის (მაგ: როცა ხარ Blog/Raw/Nocturnes გვერდზე) ---
        global $post;
        $ancestors = get_post_ancestors( $post );

        if ( $ancestors ) {
            $ancestors = array_reverse( $ancestors ); // ვატრიალებთ, რომ Home-დან დაიწყოს
            foreach ( $ancestors as $ancestor ) {
                $anc_post = get_post( $ancestor );
                $anc_path = '/' . get_page_uri( $anc_post );
                echo '<span class="zk-breadcrumb-separator">/</span>';
                echo '<a href="' . esc_url( get_permalink( $anc_post ) ) . '" data-route="' . esc_attr( $anc_path ) . '">' . esc_html( $anc_post->post_title ) . '</a>';
            }
        }

        // უშუალოდ მიმდინარე გვერდის სათაური
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . get_the_title() . '</span>';
    }

    echo '</nav>';
}

/* ============================================================
   ENABLE EXCERPTS (მოკლე აღწერების იძულებითი ჩართვა)
   ============================================================ */
function zk_force_enable_excerpts() {
    add_post_type_support( 'page', 'excerpt' );
    add_post_type_support( 'post', 'excerpt' ); // პოსტებზეც იძულებით ვრთავთ, ყოველ შემთხვევისთვის
}
add_action( 'init', 'zk_force_enable_excerpts', 999 );