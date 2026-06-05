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

    // 1. Home Link (მინიმალისტური იკონი)
    echo '<a href="' . esc_url( home_url( '/' ) ) . '" data-route="/" aria-label="Home" class="zk-home-link">';
    echo '<svg class="zk-home-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
    echo '</a>';

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

    } elseif ( is_archive() || is_search() ) {
        // --- ლოგიკა არქივებისთვის და თეგებისთვის ---
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( home_url( '/blog/' ) ) . '" data-route="/blog">Blog</a>';

        echo '<span class="zk-breadcrumb-separator">/</span>';
        if ( is_tag() ) {
            echo '<span class="zk-breadcrumb-current">Topic: ' . single_tag_title( '', false ) . '</span>';
        } elseif ( is_category() ) {
            echo '<span class="zk-breadcrumb-current">Category: ' . single_cat_title( '', false ) . '</span>';
        } else {
            echo '<span class="zk-breadcrumb-current">Archive</span>';
        }
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



/* ============================================================
   ZURAB KOSTAVA - CINEMATIC PHOTOGRAPHY GALLERY (V5 - Hardened)
   ============================================================ */

/**
 * Build a human EXIF string ("Camera • 35mm • f/1.8") for an attachment.
 * Reads only the cached _wp_attachment_metadata — no extra queries.
 */
function zk_attachment_exif( $attachment_id ) {
    $meta = wp_get_attachment_metadata( $attachment_id );
    if ( empty( $meta['image_meta'] ) ) {
        return '';
    }
    $im    = $meta['image_meta'];
    $parts = array_filter( array(
        ! empty( $im['camera'] )       ? $im['camera']              : '',
        ! empty( $im['focal_length'] ) ? $im['focal_length'] . 'mm' : '',
        ! empty( $im['aperture'] )     ? 'f/' . $im['aperture']     : '',
    ) );
    return $parts ? implode( ' • ', $parts ) : '';
}

/**
 * Photography folders we pull from. Keyed by the exact FileBird folder name,
 * mapped to the CSS filter class the front-end toggles on.
 */
function zk_gallery_folders() {
    return array(
        'Camera Photography' => 'filter-camera',
        'Mobile Photography' => 'filter-mobile',
    );
}

function zk_cinematic_gallery() {
    // Optional render cache (off by default — see zk_flush_gallery_cache notes).
    $cache_key = 'zk_gallery_html_v5';
    $use_cache = (bool) apply_filters( 'zk_gallery_cache_enabled', false );
    if ( $use_cache ) {
        $cached = get_transient( $cache_key );
        if ( is_string( $cached ) ) {
            return $cached;
        }
    }

    global $wpdb;
    $fbv_table = $wpdb->prefix . 'fbv';
    $rel_table = $wpdb->prefix . 'fbv_attachment_folder';

    // Guard: is FileBird installed? esc_like() so "_" isn't read as a wildcard.
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $fbv_table ) ) );
    if ( $found !== $fbv_table ) {
        return '<p class="page__content" style="color:#ff5555;">FileBird tables not found.</p>';
    }

    $folder_map   = zk_gallery_folders();
    $folder_names = array_keys( $folder_map );

    // (1) Folder names → ids — one prepared query.
    $name_ph = implode( ', ', array_fill( 0, count( $folder_names ), '%s' ) );
    $folders = $wpdb->get_results(
        $wpdb->prepare( "SELECT id, name FROM {$fbv_table} WHERE name IN ($name_ph)", $folder_names )
    );
    if ( empty( $folders ) ) {
        return '<p class="page__content">Folders not found!</p>';
    }

    $folder_class = array(); // folder_id => filter-xxx
    foreach ( $folders as $folder ) {
        if ( isset( $folder_map[ $folder->name ] ) ) {
            $folder_class[ (int) $folder->id ] = $folder_map[ $folder->name ];
        }
    }

    // (2) Folder ids → attachments (+ category map) — one prepared query.
    $folder_ids = array_keys( $folder_class );
    $id_ph      = implode( ', ', array_fill( 0, count( $folder_ids ), '%d' ) );
    $rows       = $wpdb->get_results(
        $wpdb->prepare( "SELECT attachment_id, folder_id FROM {$rel_table} WHERE folder_id IN ($id_ph)", $folder_ids )
    );
    if ( empty( $rows ) ) {
        return '<p class="page__content">Folders are empty!</p>';
    }

    $category_map = array(); // attachment_id => filter-xxx (first folder wins)
    foreach ( $rows as $row ) {
        $att = (int) $row->attachment_id;
        $fid = (int) $row->folder_id;
        if ( ! isset( $category_map[ $att ] ) && isset( $folder_class[ $fid ] ) ) {
            $category_map[ $att ] = $folder_class[ $fid ];
        }
    }

    // (3) Fetch attachments. WP_Query primes the post + meta caches in bulk,
    //     so every helper below reads cache instead of hitting the DB again.
    $query = new WP_Query( array(
        'post_type'              => 'attachment',
        'post_status'            => 'inherit',
        'post_mime_type'         => 'image',
        'posts_per_page'         => -1,
        'post__in'               => array_keys( $category_map ),
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'update_post_term_cache' => false,
    ) );

    $output  = '<div class="zk-gallery-wrapper">';
    $output .= '<div class="zk-gallery-filters" role="group" aria-label="Filter photography">';
    $output .= '<button class="zk-filter-btn is-active" type="button" data-filter="all" aria-pressed="true">All</button>';
    $output .= '<button class="zk-filter-btn" type="button" data-filter="filter-camera" aria-pressed="false">Camera</button>';
    $output .= '<button class="zk-filter-btn" type="button" data-filter="filter-mobile" aria-pressed="false">Mobile</button>';
    $output .= '</div>';

    $output .= '<div class="zk-gallery-grid" id="zkGalleryGrid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $image_id = get_the_ID();

        $full_img  = wp_get_attachment_image_url( $image_id, 'full' );
        $thumb_img = wp_get_attachment_image_url( $image_id, 'thumbnail' ); // light strip image
        $cat_class = isset( $category_map[ $image_id ] ) ? $category_map[ $image_id ] : 'all';
        $exif_text = zk_attachment_exif( $image_id );

        $img_html = wp_get_attachment_image( $image_id, 'large', false, array(
            'data-full'  => esc_url( $full_img ),
            'data-thumb' => esc_url( $thumb_img ? $thumb_img : $full_img ),
            'data-exif'  => esc_attr( $exif_text ),
            'class'      => 'zk-grid-photo',
        ) );

        $output .= '<div class="zk-gallery-item ' . esc_attr( $cat_class ) . '" data-category="' . esc_attr( $cat_class ) . '">';
        $output .= '<div class="zk-gallery-image-wrap">' . $img_html . '</div>';
        $output .= '</div>';
    }
    wp_reset_postdata();
    $output .= '</div></div>';

    // Cinematic lightbox — one instance, rebuilt with the view on SPA swaps.
    $output .= '<div class="zk-lightbox" id="zkLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Photo viewer">';
    $output .= '<button class="zk-lightbox-close" type="button" aria-label="Close">✕</button>';
    $output .= '<button class="zk-lightbox-arrow zk-lightbox-prev" type="button" aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>';
    $output .= '<button class="zk-lightbox-arrow zk-lightbox-next" type="button" aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>';
    $output .= '<img class="zk-lightbox-img" src="" alt="" decoding="async">';
    $output .= '<div class="zk-lightbox-exif" id="zkLightboxExif"></div>';
    $output .= '<div class="zk-lightbox-thumbs" id="zkLightboxThumbs"></div>';
    $output .= '</div>';

    if ( $use_cache ) {
        set_transient( $cache_key, $output, 6 * HOUR_IN_SECONDS );
    }

    return $output;
}
add_shortcode( 'zk_photography', 'zk_cinematic_gallery' );

/**
 * Optional gallery render cache — OFF by default.
 * Enable with:  add_filter( 'zk_gallery_cache_enabled', '__return_true' );
 * These hooks bust it whenever the media library changes. Note: moving an
 * image between FileBird folders fires no core hook, so the 6h TTL is the
 * backstop there — flush manually (or wait it out) after re-foldering.
 */
function zk_flush_gallery_cache() {
    delete_transient( 'zk_gallery_html_v5' );
}
add_action( 'add_attachment',    'zk_flush_gallery_cache' );
add_action( 'edit_attachment',   'zk_flush_gallery_cache' );
add_action( 'delete_attachment', 'zk_flush_gallery_cache' );



/* ============================================================
   🎵 MUSIC TIMELINE - FULL MODULE (CPT + META + SHORTCODE)
   ============================================================ */

// 1. მენიუს შექმნა (Custom Post Type)
function zk_register_music_timeline_cpt() {
    $labels = array(
            'name'               => 'Music Timeline',
            'singular_name'      => 'Release',
            'menu_name'          => 'Music Timeline',
            'add_new'            => 'Add New Release',
            'add_new_item'       => 'Add New Release',
            'edit_item'          => 'Edit Release',
            'all_items'          => 'All Releases',
    );
    $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-format-audio',
            'supports'           => array( 'title', 'editor' ),
            'has_archive'        => false,
    );
    register_post_type( 'zk_music_release', $args );
}
add_action( 'init', 'zk_register_music_timeline_cpt' );

// 2. ველების (Meta Boxes) ვიზუალის აწყობა ედიტორში
function zk_music_add_meta_box() {
    add_meta_box( 'zk_music_details', 'Release Details', 'zk_music_meta_callback', 'zk_music_release', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_music_add_meta_box' );

function zk_music_meta_callback( $post ) {
    wp_nonce_field( 'zk_music_save_meta_data', 'zk_music_meta_nonce' );

    $subtitle    = get_post_meta( $post->ID, '_zk_subtitle', true );
    $date        = get_post_meta( $post->ID, '_zk_display_date', true );
    $genre       = get_post_meta( $post->ID, '_zk_genre', true ); // <--- Theme შეიცვალა Genre-თი
    $media_type  = get_post_meta( $post->ID, '_zk_media_type', true );
    $media_id    = get_post_meta( $post->ID, '_zk_media_id', true );
    $spotify_url = get_post_meta( $post->ID, '_zk_spotify_url', true );
    $more_url    = get_post_meta( $post->ID, '_zk_more_url', true ); // <--- ახალი ცვლადი
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
        <div>
            <label><strong>Display Date</strong> (e.g. 30.03.2026)</label><br>
            <input type="text" name="zk_display_date" value="<?php echo esc_attr( $date ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Subtitle</strong></label><br>
            <input type="text" name="zk_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Genre / Tag</strong> (e.g. Piano, Ambient, Nocturne)</label><br>
            <input type="text" name="zk_genre" value="<?php echo esc_attr( $genre ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Media Type</strong></label><br>
            <select name="zk_media_type" style="width:100%; margin-top:5px;">
                <option value="youtube" <?php selected( $media_type, 'youtube' ); ?>>YouTube</option>
                <option value="spotify" <?php selected( $media_type, 'spotify' ); ?>>Spotify</option>
                <option value="none" <?php selected( $media_type, 'none' ); ?>>None</option>
            </select>

        </div>
        <div>
            <label><strong>Media ID</strong> (YouTube/Spotify ID)</label><br>
            <input type="text" name="zk_media_id" value="<?php echo esc_attr( $media_id ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Spotify Full URL</strong> (Leave empty for disabled button)</label><br>
            <input type="url" name="zk_spotify_url" value="<?php echo esc_url( $spotify_url ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div style="grid-column: 1 / -1;">
            <label><strong>"See More" URL</strong> (Link to a blog post or external source. Leave empty to hide)</label><br>
            <input type="url" name="zk_more_url" value="<?php echo esc_url( $more_url ); ?>" style="width:100%; margin-top:5px;" />
        </div>
    </div>
    <p style="color: #666; font-size: 13px; margin-top: 15px;"><em>Note: The main title and description of the release should be written in the standard WordPress title and text editor above.</em></p>
    <?php
}

// 3. მონაცემების უსაფრთხოდ შენახვა ბაზაში
function zk_music_save_meta_data( $post_id ) {
    if ( ! isset( $_POST['zk_music_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_music_meta_nonce'], 'zk_music_save_meta_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array(
            'zk_subtitle'     => '_zk_subtitle',
            'zk_display_date' => '_zk_display_date',
            'zk_genre'        => '_zk_genre', // <--- Theme შეიცვალა Genre-თი
            'zk_media_type'   => '_zk_media_type',
            'zk_media_id'     => '_zk_media_id',
            'zk_spotify_url'  => '_zk_spotify_url',
            'zk_more_url'     => '_zk_more_url',
    );

    foreach ( $fields as $post_key => $meta_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
        }
    }
}
add_action( 'save_post', 'zk_music_save_meta_data' );

// 4. დინამიური შორთკოდი და JS-თან დაკავშირება
function zk_music_timeline_shortcode() {
    $args = array(
            'post_type'      => 'zk_music_release',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
    );
    $query = new WP_Query( $args );
    $music_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $music_data[] = array(
                    'id'          => 'release-' . $post_id,
                    'displayDate' => get_post_meta( $post_id, '_zk_display_date', true ),
                    'genre'       => get_post_meta( $post_id, '_zk_genre', true ), // <--- აქაც Genre
                    'title'       => get_the_title(),
                    'subtitle'    => get_post_meta( $post_id, '_zk_subtitle', true ),
                    'description' => do_shortcode( wpautop( get_the_content() ) ),
                    'mediaType'   => get_post_meta( $post_id, '_zk_media_type', true ),
                    'mediaId'     => get_post_meta( $post_id, '_zk_media_id', true ),
                    'spotifyUrl'  => get_post_meta( $post_id, '_zk_spotify_url', true ),
                    'moreUrl'     => get_post_meta( $post_id, '_zk_more_url', true ),
            );
        }
        wp_reset_postdata();
    }

    $json_data = wp_json_encode( $music_data );
    // მონაცემებს ვაქცევთ HTML-ისთვის უსაფრთხო ტექსტად და ვსვამთ პირდაპირ div-ში
    $escaped_json = htmlspecialchars( $json_data, ENT_QUOTES, 'UTF-8' );

    $output = '<div class="zk-timeline-wrapper" id="zkMusicTimeline" data-music-payload="' . $escaped_json . '"></div>';

    return $output;
}
add_shortcode( 'zk_music', 'zk_music_timeline_shortcode' );

/* ============================================================
   ABOUT PAGE - DYNAMIC TABS SHORTCODE
   ============================================================ */
function zk_about_page_shortcode() {
    ob_start(); ?>

    <div class="zk-about-wrapper">
        <div class="zk-tabs-nav-container">
            <div class="zk-tabs-nav">
                <div class="zk-tab-highlight"></div>
                <button class="zk-tab-btn active" data-target="tab-identity">Identity</button>
                <button class="zk-tab-btn" data-target="tab-journey">The Journey</button>
                <button class="zk-tab-btn" data-target="tab-captures">Life & Captures</button>
            </div>
        </div>

        <div class="zk-tabs-content">

            <!-- TAB 1: IDENTITY (Bento Box Dashboard) -->
            <div class="zk-tab-panel active" id="tab-identity">
                <div class="zk-identity-dashboard">

                    <!-- Box 1: Vitals & Connect (Full Width with Photo) -->
                    <!-- Box 1: Vitals & Connect (Full Width with Photo) -->
                    <div class="zk-bento-box zk-bento-profile zk-col-span-2">

                        <!-- 1. HEADER (გამოტანილია გარეთ, რომ სრულ სიგანეზე გაიშალოს და ხაზი გაავლოს) -->
                        <div class="zk-bento-header">
                            <div class="zk-header-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <h3>Vitals & Connect</h3>
                            </div>
                            <p class="zk-bento-desc">Zurab Kostava's digital ID, where he objectively looks much better than on his actual passport.</p>
                        </div>

                        <!-- 2. CONTENT WRAPPER (ინფორმაცია და ფოტო უკვე ამის შიგნითაა და ზედა კიდით სწორდება) -->
                        <div class="zk-profile-inner">

                            <!-- მარცხენა მხარე: ინფორმაცია -->
                            <div class="zk-profile-content">
                                <ul class="zk-vitals-list">
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <div class="zk-vital-text"><strong>Born:</strong> <span>DD.MM.YYYY</span></div>
                                    </li>
                                    <!-- ახალი: Origin / დაბადების ადგილი კომპასის იკონით -->
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                                        <div class="zk-vital-text"><strong>Origin:</strong> <span>Ozurgeti, Guria, Georgia</span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        <div class="zk-vital-text"><strong>Base:</strong> <span>Tbilisi, Georgia</span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                                        <div class="zk-vital-text"><strong>Studio:</strong> <span>Creative Lead @ <a href="https://zurabkostava.com" target="_blank">Kostava Creative</a></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        <div class="zk-vital-text"><strong>Position:</strong> <span>Web Designer @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        <div class="zk-vital-text"><strong>Archetype:</strong> <span>Composer, Visual Artist, Tech Geek</span></div>
                                    </li>
                                </ul>

                                <div class="zk-social-bar">
                                    <a href="#" class="zk-social-btn" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                                    <a href="#" class="zk-social-btn" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                                    <a href="#" class="zk-social-btn" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
                                    <a href="#" class="zk-social-btn" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg></a>
                                    <a href="#" class="zk-social-btn" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg></a>
                                </div>
                            </div>

                            <!-- მარჯვენა მხარე: ID ფოტო -->
                            <div class="zk-profile-photo">
                                <!-- აქ ჩასვი შენი ფოტოს რეალური URL -->
                                <img src="https://via.placeholder.com/150x200" alt="Zurab Kostava" />
                            </div>

                        </div>
                    </div>

                    <!-- Box 2: SKILLS -->
                    <div class="zk-bento-box zk-bento-skills zk-col-span-2">
                        <div class="zk-bento-header">
                            <div class="zk-header-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                <h3>Skills</h3>
                            </div>
                            <p class="zk-bento-desc">A brief list of capabilities. This isn't a flex for the portfolio—it's mostly here so he doesn't forget what he can actually do.</p>
                        </div>
                        <div class="zk-tags-container">
                            <span class="zk-tag">Music Production (Ableton, Cubase)</span>
                            <span class="zk-tag">Cinematography & Color Grading</span>
                            <span class="zk-tag">UI/UX Design (Figma)</span>
                            <span class="zk-tag">Sound Design</span>
                            <span class="zk-tag">Web Technologies</span>
                            <span class="zk-tag">AI Workflows & Generation</span>
                        </div>
                    </div>

                    <!-- Box 3: The Curated Mind -->
                    <div class="zk-bento-box zk-bento-favorites zk-col-span-2">
                        <div class="zk-bento-header">
                            <div class="zk-header-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                <h3>The Curated Mind</h3>
                            </div>
                            <p class="zk-bento-desc">Cultural influences. The definitive list that proves his exceptional taste in media (and yes, he is quite proud of it).</p>
                        </div>

                        <div class="zk-favorites-grid">

                            <!-- 1. Cinema -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                                    Cinema
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">Interstellar</a></li>
                                    <li><a href="#" target="_blank">Abre los ojos</a></li>
                                    <li><a href="#" target="_blank">Stay</a></li>
                                    <li><a href="#" target="_blank">The Truman Show</a></li>
                                    <li><a href="#" target="_blank">Inception</a></li>
                                </ol>
                            </div>

                            <!-- 2. Series -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
                                    Series
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Series 1]</a></li>
                                    <li><a href="#" target="_blank">[Series 2]</a></li>
                                    <li><a href="#" target="_blank">[Series 3]</a></li>
                                    <li><a href="#" target="_blank">[Series 4]</a></li>
                                    <li><a href="#" target="_blank">[Series 5]</a></li>
                                </ol>
                            </div>

                            <!-- 3. Books -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                    Books
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Book 1]</a></li>
                                    <li><a href="#" target="_blank">[Book 2]</a></li>
                                    <li><a href="#" target="_blank">[Book 3]</a></li>
                                    <li><a href="#" target="_blank">[Book 4]</a></li>
                                    <li><a href="#" target="_blank">[Book 5]</a></li>
                                </ol>
                            </div>

                            <!-- 4. Writers -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg>
                                    Writers
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Writer 1]</a></li>
                                    <li><a href="#" target="_blank">[Writer 2]</a></li>
                                    <li><a href="#" target="_blank">[Writer 3]</a></li>
                                    <li><a href="#" target="_blank">[Writer 4]</a></li>
                                    <li><a href="#" target="_blank">[Writer 5]</a></li>
                                </ol>
                            </div>

                            <!-- 5. Directors -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                                    Directors
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">Christopher Nolan</a></li>
                                    <li><a href="#" target="_blank">Denis Villeneuve</a></li>
                                    <li><a href="#" target="_blank">[Director 3]</a></li>
                                    <li><a href="#" target="_blank">[Director 4]</a></li>
                                    <li><a href="#" target="_blank">[Director 5]</a></li>
                                </ol>
                            </div>

                            <!-- 6. Actors -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    Actors
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">Matthew McConaughey</a></li>
                                    <li><a href="#" target="_blank">[Actor 2]</a></li>
                                    <li><a href="#" target="_blank">[Actor 3]</a></li>
                                    <li><a href="#" target="_blank">[Actor 4]</a></li>
                                    <li><a href="#" target="_blank">[Actor 5]</a></li>
                                </ol>
                            </div>

                            <!-- 7. Musical Artists -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
                                    Artists
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">Hans Zimmer</a></li>
                                    <li><a href="#" target="_blank">Nils Frahm</a></li>
                                    <li><a href="#" target="_blank">[Artist 3]</a></li>
                                    <li><a href="#" target="_blank">[Artist 4]</a></li>
                                    <li><a href="#" target="_blank">[Artist 5]</a></li>
                                </ol>
                            </div>

                            <!-- 8. Musical Bands -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    Bands
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Band 1]</a></li>
                                    <li><a href="#" target="_blank">[Band 2]</a></li>
                                    <li><a href="#" target="_blank">[Band 3]</a></li>
                                    <li><a href="#" target="_blank">[Band 4]</a></li>
                                    <li><a href="#" target="_blank">[Band 5]</a></li>
                                </ol>
                            </div>

                            <!-- 9. Albums -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg>
                                    Albums
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Album 1]</a></li>
                                    <li><a href="#" target="_blank">[Album 2]</a></li>
                                    <li><a href="#" target="_blank">[Album 3]</a></li>
                                    <li><a href="#" target="_blank">[Album 4]</a></li>
                                    <li><a href="#" target="_blank">[Album 5]</a></li>
                                </ol>
                            </div>

                            <!-- 10. Songs -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2z"></path><path d="M3 19a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"></path></svg>
                                    Songs
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">Cornfield Chase</a></li>
                                    <li><a href="#" target="_blank">Time</a></li>
                                    <li><a href="#" target="_blank">[Song 3]</a></li>
                                    <li><a href="#" target="_blank">[Song 4]</a></li>
                                    <li><a href="#" target="_blank">[Song 5]</a></li>
                                </ol>
                            </div>

                            <!-- 11. Tech Nerds / Visionaries -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                    Tech Nerds
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Nerd 1]</a></li>
                                    <li><a href="#" target="_blank">[Nerd 2]</a></li>
                                    <li><a href="#" target="_blank">[Nerd 3]</a></li>
                                    <li><a href="#" target="_blank">[Nerd 4]</a></li>
                                    <li><a href="#" target="_blank">[Nerd 5]</a></li>
                                </ol>
                            </div>

                            <!-- 12. Scientists -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(45 12 12)"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(-45 12 12)"></ellipse></svg>
                                    Scientists
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Scientist 1]</a></li>
                                    <li><a href="#" target="_blank">[Scientist 2]</a></li>
                                    <li><a href="#" target="_blank">[Scientist 3]</a></li>
                                    <li><a href="#" target="_blank">[Scientist 4]</a></li>
                                    <li><a href="#" target="_blank">[Scientist 5]</a></li>
                                </ol>
                            </div>

                            <!-- 13. Athletes -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                    Athletes
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Athlete 1]</a></li>
                                    <li><a href="#" target="_blank">[Athlete 2]</a></li>
                                    <li><a href="#" target="_blank">[Athlete 3]</a></li>
                                    <li><a href="#" target="_blank">[Athlete 4]</a></li>
                                    <li><a href="#" target="_blank">[Athlete 5]</a></li>
                                </ol>
                            </div>

                            <!-- 14. Models -->
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                    Models
                                </h4>
                                <ol>
                                    <li><a href="#" target="_blank">[Model 1]</a></li>
                                    <li><a href="#" target="_blank">[Model 2]</a></li>
                                    <li><a href="#" target="_blank">[Model 3]</a></li>
                                    <li><a href="#" target="_blank">[Model 4]</a></li>
                                    <li><a href="#" target="_blank">[Model 5]</a></li>
                                </ol>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <div class="zk-tab-panel" id="tab-journey">
                <div class="zk-timeline-body">
                    <h3>The Creative Process</h3>
                    <p>This is where you can dive deep into your story. Talk about your connection to music, cinematography, and technology.</p>
                    <p>Explain how you use tools to build nocturnes and aubades, and what inspires your visual projects.</p>
                    <blockquote>"A place for a nice, meaningful quote about your art."</blockquote>
                </div>
            </div>

            <div class="zk-tab-panel" id="tab-captures">
                <div class="zk-bento-header" style="margin-bottom: 30px;">
                    <div class="zk-header-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        <h3>Life & Captures</h3>
                    </div>
                    <p class="zk-bento-desc">Behind the scenes. No renders, no code—just real life captured through a lens.</p>
                </div>

                <div class="zk-filebird-gallery-wrapper">
                    <?php
                    // აქ იძახებს ჩვენს დაწერილ PHP ფუნქციას.
                    // !მნიშვნელოვანია! რიცხვი 2 უნდა შეცვალო შენი ფოლდერის ნამდვილი ID-ით
                    echo zk_get_filebird_gallery( 5 );
                    ?>
                </div>
            </div>

        </div>
    </div>

    <?php return ob_get_clean();
}
add_shortcode( 'zk_about', 'zk_about_page_shortcode' );


/* ============================================================
   FILEBIRD CUSTOM GALLERY FETCHER (LIGHTBOX SUPPORT)
   ============================================================ */
/* ============================================================
   FILEBIRD CUSTOM GALLERY FETCHER (NATIVE PHOTOGRAPHY STYLE)
   ============================================================ */
function zk_get_filebird_gallery( $folder_id ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'fbv_attachment_folder';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        return '<p style="color:var(--text-dim);">FileBird database table not found.</p>';
    }

    $attachment_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT attachment_id FROM $table_name WHERE folder_id = %d ORDER BY attachment_id DESC", intval( $folder_id ) )
    );

    if ( empty( $attachment_ids ) ) {
        return '<p style="color:var(--text-dim);">No photos found in this folder.</p>';
    }

    // ვიყენებთ ზუსტად იმავე კლასებს, რაც photography გვერდზე გაქვს (მაგალითად: zk-masonry-gallery ან მსგავსი)
    $output = '<div class="zk-gallery-grid">';

    foreach ( $attachment_ids as $id ) {
        $img_thumbnail = wp_get_attachment_image_url( $id, 'large' );
        $img_full = wp_get_attachment_image_url( $id, 'full' );

        if ( $img_thumbnail && $img_full ) {
            $output .= '<div class="zk-gallery-item">';
            // კლასი შეცვლილია სტანდარტულზე, რომელსაც შენი ძირითადი სკრიპტი კითხულობს
            $output .= '<a href="' . esc_url( $img_full ) . '" class="zk-photography-trigger">';
            $output .= '<img src="' . esc_url( $img_thumbnail ) . '" alt="Capture" loading="lazy" />';
            $output .= '<div class="zk-gallery-hover-overlay"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></div>';
            $output .= '</a>';
            $output .= '</div>';
        }
    }

    $output .= '</div>';

    return $output;
}