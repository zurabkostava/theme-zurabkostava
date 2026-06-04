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
    $output .= '<button class="zk-filter-btn is-active" type="button" data-filter="all" aria-pressed="true">All Works</button>';
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
   MUSIC TIMELINE SHORTCODE
   ============================================================ */
function zk_music_timeline_shortcode() {
    return '<div class="zk-timeline-wrapper" id="zkMusicTimeline"></div>';
}
add_shortcode( 'zk_music', 'zk_music_timeline_shortcode' );