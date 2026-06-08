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

// ============================================================
// GLOBAL MOBILE UX FIX: KILL ANDROID CHROME TAP HIGHLIGHT
// ============================================================
function zk_global_mobile_ux_fix() {
    ?>
    <style id="zk-global-mobile-fix">
        html, body, * {
            -webkit-tap-highlight-color: transparent !important;
            -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
        }
        /* Make sure buttons don't have default focus outlines on touch */
        a, button, input, select, textarea {
            outline: none !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'zk_global_mobile_ux_fix', 999);

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
            
            // ვითვალისწინებთ WordPress-ის "Open in new tab" პარამეტრს
            $target_attr = ! empty( $item->target ) ? $item->target : ( $is_external ? '_blank' : '' );
            $target_html = ! empty( $target_attr ) ? ' target="' . esc_attr( $target_attr ) . '" rel="noopener noreferrer"' : '';

            $output .= '<a class="' . esc_attr( $link_class ) . '" data-route="' . esc_attr( $path ) . '" href="' . esc_url( $url ) . '"' . $aria . $target_html . '>';
            $output .= esc_html( $item->title );

            // თუ გარე ლინკია ან ახალ ტაბში იხსნება, ვამატებთ მინიმალისტურ ისრის იკონს
            if ( $target_attr === '_blank' ) {
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
            'post_status'    => 'publish',
            'no_found_rows'  => true
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
        // ქარდს ვუმატებთ data-time ატრიბუტს
        $output .= '<a href="' . esc_url( $link ) . '" class="zk-grid-card" data-route="' . esc_attr( $path ) . '" data-time="' . esc_attr( $timestamp ) . '">';
        $output .= '<div class="zk-card-image">';
        if ( $img_url ) {
            $output .= '<img src="' . esc_url( $img_url ) . '" loading="lazy" decoding="async" alt="' . esc_attr( $title ) . '" class="zk-card-img">';
        }
        $output .= '</div>';
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
            'no_found_rows'  => true
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
function zk_render_fav_list($option_key) {
    $data = get_option($option_key, '');
    if (empty(trim($data))) {
        echo '<li><a href="#">[TBD]</a></li>';
        return;
    }
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $parts = explode('|', $line);
        $name = trim($parts[0]);
        $url = isset($parts[1]) ? trim($parts[1]) : '#';
        echo '<li><a href="' . esc_url($url) . '" target="_blank">' . esc_html($name) . '</a></li>';
    }
}

function zk_about_page_shortcode() {
    ob_start(); ?>

    <div class="zk-about-wrapper">
        <div class="zk-tabs-nav-container">
            <div class="zk-tabs-nav">
                <div class="zk-tab-highlight"></div>
                <button class="zk-tab-btn active" data-target="tab-identity">Identity</button>
                <button class="zk-tab-btn" data-target="tab-monologue">Monologue</button>
                <button class="zk-tab-btn" data-target="tab-gallery">Gallery</button>
            </div>
        </div>

        <div class="zk-tabs-content">

            <!-- TAB 1: IDENTITY (Bento Box Dashboard) -->
            <div class="zk-tab-panel active" id="tab-identity">
                <div class="zk-identity-dashboard">

                    <!-- Box 1: Vitals & Connect -->
                    <div class="zk-bento-box zk-bento-profile zk-col-span-2">
                        <div class="zk-bento-header">
                            <div class="zk-header-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <h3>Vitals & Connect</h3>
                            </div>
                            <p class="zk-bento-desc"><?php echo esc_html(get_option('zk_desc_vitals', "Zurab Kostava's digital ID, where he objectively looks much better than on his actual passport.")); ?></p>
                        </div>

                        <div class="zk-profile-inner">
                            <div class="zk-profile-content">
                                <ul class="zk-vitals-list">
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <div class="zk-vital-text"><strong>Born:</strong> <span><?php echo esc_html(get_option('zk_vital_born', 'DD.MM.YYYY')); ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                                        <div class="zk-vital-text"><strong>Origin:</strong> <span><?php echo esc_html(get_option('zk_vital_origin', 'Ozurgeti, Guria, Georgia')); ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        <div class="zk-vital-text"><strong>Base:</strong> <span><?php echo esc_html(get_option('zk_vital_base', 'Tbilisi, Georgia')); ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                                        <div class="zk-vital-text"><strong>Studio:</strong> <span><?php echo wp_kses_post(get_option('zk_vital_studio', 'Creative Lead @ <a href="https://zurabkostava.com" target="_blank">Kostava Creative</a>')); ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        <div class="zk-vital-text"><strong>Position:</strong> <span><?php echo wp_kses_post(get_option('zk_vital_position', 'Web Designer @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>')); ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        <div class="zk-vital-text"><strong>Archetype:</strong> <span><?php echo esc_html(get_option('zk_vital_archetype', 'Composer, Visual Artist, Tech Geek')); ?></span></div>
                                    </li>

                                </ul>
                            </div> <!-- /.zk-profile-content-ის დასასრული -->

                            <div class="zk-profile-photo">
                                <img src="<?php echo esc_url(get_option('zk_profile_img', 'https://via.placeholder.com/150x200')); ?>" alt="Zurab Kostava" />
                            </div>
                        </div> <!-- /.zk-profile-inner-ის დასასრული -->

                        <div class="zk-connect-footer">
                            <div class="zk-social-bar">
                                <a href="<?php echo esc_url(get_option('zk_social_ig', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_fb', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_x', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="X (Twitter)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_linkedin', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_youtube', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_spotify', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Spotify"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.24 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.24 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.6.18-1.2.72-1.381 4.26-1.261 11.28-1.02 15.721 1.621.54.3.72.96.42 1.5-.3.54-.96.72-1.56.36z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_bandcamp', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Bandcamp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_medium', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Medium"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zM20.96 12c0 3.54-1.51 6.42-3.38 6.42-1.87 0-3.39-2.88-3.39-6.42s1.52-6.42 3.39-6.42c1.87 0 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z"/></svg></a>
                            </div>
                            <div class="zk-email-bar">
                                <span class="zk-email-label">Contact:</span>
                                <div class="zk-email-list">
                                    <a href="mailto:<?php echo esc_attr(get_option('zk_vital_email_1', 'zurab@kostavacreative.com')); ?>" class="zk-email-link">
                                        <?php echo esc_html(get_option('zk_vital_email_1', 'zurab@kostavacreative.com')); ?>
                                    </a>
                                    <a href="mailto:<?php echo esc_attr(get_option('zk_vital_email_2', 'zurabkostava1@gmail.com')); ?>" class="zk-email-link">
                                        <?php echo esc_html(get_option('zk_vital_email_2', 'zurabkostava1@gmail.com')); ?>
                                    </a>
                                </div>
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
                            <p class="zk-bento-desc"><?php echo esc_html(get_option('zk_desc_skills', "A brief list of capabilities. This isn't a flex for the portfolio—it's mostly here so he doesn't forget what he can actually do.")); ?></p>
                        </div>
                        <div class="zk-tags-container">
                            <?php
                            $skills_raw = get_option('zk_skills', 'Music Production, Cinematography & Color Grading, UI/UX Design, Sound Design, Web Technologies, AI Workflows');
                            $skills = array_filter(array_map('trim', explode(',', $skills_raw)));
                            foreach ($skills as $skill) {
                                echo '<span class="zk-tag">' . esc_html($skill) . '</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Box 3: The Curated Mind -->
                    <div class="zk-bento-box zk-bento-favorites zk-col-span-2">
                        <div class="zk-bento-header">
                            <div class="zk-header-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                <h3>The Curated Mind</h3>
                            </div>
                            <p class="zk-bento-desc"><?php echo esc_html(get_option('zk_desc_favorites', "Cultural influences. The definitive list that proves his exceptional taste in media (and yes, he is quite proud of it).")); ?></p>
                        </div>

                        <div class="zk-favorites-grid">
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg> Cinema</h4>
                                <ol><?php zk_render_fav_list('zk_fav_cinema'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg> Series</h4>
                                <ol><?php zk_render_fav_list('zk_fav_series'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> Books</h4>
                                <ol><?php zk_render_fav_list('zk_fav_books'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg> Writers</h4>
                                <ol><?php zk_render_fav_list('zk_fav_writers'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> Directors</h4>
                                <ol><?php zk_render_fav_list('zk_fav_directors'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> Actors</h4>
                                <ol><?php zk_render_fav_list('zk_fav_actors'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg> Artists</h4>
                                <ol><?php zk_render_fav_list('zk_fav_artists'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Bands</h4>
                                <ol><?php zk_render_fav_list('zk_fav_bands'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg> Albums</h4>
                                <ol><?php zk_render_fav_list('zk_fav_albums'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2z"></path><path d="M3 19a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"></path></svg> Songs</h4>
                                <ol><?php zk_render_fav_list('zk_fav_songs'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg> Tech Nerds</h4>
                                <ol><?php zk_render_fav_list('zk_fav_nerds'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(45 12 12)"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(-45 12 12)"></ellipse></svg> Scientists</h4>
                                <ol><?php zk_render_fav_list('zk_fav_scientists'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg> Athletes</h4>
                                <ol><?php zk_render_fav_list('zk_fav_athletes'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> Models</h4>
                                <ol><?php zk_render_fav_list('zk_fav_models'); ?></ol>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="zk-tab-panel" id="tab-monologue">
                <!-- აქ განგებ ვიყენებთ page__content კლასს, რომ შენი დაწერილი ულამაზესი ტიპოგრაფია (style.css-დან) ავტომატურად მოერგოს -->
                <div class="page__content">
                    <?php
                    $monologue = get_option('zk_monologue_content', "<h3>The Monologue</h3>\n<p>This is where you can dive deep into your story.</p>");

                    // apply_filters('the_content', ...) უზრუნველყოფს, რომ ვორდპრესმა სწორად აღიქვას შენი დაწერილი აბზაცები და ვიდეოების/ფოტოების ლინკები
                    echo apply_filters('the_content', $monologue);
                    ?>
                </div>
            </div>

            <div class="zk-tab-panel" id="tab-gallery">
                <div class="zk-bento-header" style="margin-bottom: 30px;">
                    <div class="zk-header-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        <h3>Gallery</h3>
                    </div>
                    <p class="zk-bento-desc"><?php echo esc_html(get_option('zk_desc_life', "Behind the scenes. No renders, no code—just real life captured through a lens.")); ?></p>
                </div>
                <div class="zk-filebird-gallery-wrapper">
                    <?php echo zk_get_filebird_gallery( 5 ); ?>
                </div>
            </div>

        </div><!-- /.zk-tabs-content -->

        <div class="zk-lightbox" id="zkLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Photo viewer">
            <button class="zk-lightbox-close" type="button" aria-label="Close">✕</button>
            <button class="zk-lightbox-arrow zk-lightbox-prev" type="button" aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
            <button class="zk-lightbox-arrow zk-lightbox-next" type="button" aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
            <img class="zk-lightbox-img" src="" alt="" decoding="async">
            <div class="zk-lightbox-exif" id="zkLightboxExif"></div>
            <div class="zk-lightbox-thumbs" id="zkLightboxThumbs"></div>
        </div>
    </div><!-- /.zk-about-wrapper -->

    <?php return ob_get_clean();
}
add_shortcode( 'zk_about', 'zk_about_page_shortcode' );


/* ============================================================
   FILEBIRD CUSTOM GALLERY FETCHER (CINEMATIC LIGHTBOX)
   Emits the same GRID markup as [zk_photography]; the shared
   initGallery() in app.js drives it identically. The #zkLightbox
   overlay is rendered separately by [zk_about], OUTSIDE the
   (transformed) tab panels — see the note at the return below.
   ============================================================ */
function zk_get_filebird_gallery( $folder_id ) {
    global $wpdb;

    // FileBird-ის ცხრილი
    $table_name = $wpdb->prefix . 'fbv_attachment_folder';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        return '<p style="color:var(--text-dim);">FileBird database table not found.</p>';
    }

    // ვიღებთ ID-ებს
    $attachment_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT attachment_id FROM $table_name WHERE folder_id = %d ORDER BY attachment_id DESC", intval( $folder_id ) )
    );

    if ( empty( $attachment_ids ) ) {
        return '<p style="color:var(--text-dim);">No photos found in this FileBird folder.</p>';
    }

    // ── Masonry grid — identical structure to the photography gallery. ──
    $output  = '<div class="zk-gallery-wrapper">';
    $output .= '<div class="zk-gallery-grid" id="zkAboutGallery">';

    foreach ( $attachment_ids as $id ) {
        $full_img  = wp_get_attachment_image_url( $id, 'full' );
        $thumb_img = wp_get_attachment_image_url( $id, 'thumbnail' ); // light strip image
        if ( ! $full_img ) {
            continue;
        }

        // Same image contract initGallery() reads: data-full (lightbox), data-thumb (strip).
        // Life & Captures has no EXIF, so data-exif stays empty.
        $img_html = wp_get_attachment_image( $id, 'large', false, array(
                'data-full'  => esc_url( $full_img ),
                'data-thumb' => esc_url( $thumb_img ? $thumb_img : $full_img ),
                'data-exif'  => '',
                'class'      => 'zk-grid-photo',
                'alt'        => 'Zurab Kostava Capture',
        ) );

        $output .= '<div class="zk-gallery-item">';
        $output .= '<div class="zk-gallery-image-wrap">' . $img_html . '</div>';
        $output .= '</div>';
    }

    $output .= '</div></div>';

    // NOTE: the #zkLightbox overlay is emitted by zk_about_page_shortcode() OUTSIDE the
    // tab panels — .zk-tab-panel's slide transform would otherwise trap position:fixed,
    // framing the viewer inside the panel instead of filling the viewport.
    return $output;
}

/* Life & Captures now uses the theme's own cinematic lightbox (see
   zk_get_filebird_gallery + initGallery in app.js) — PhotoSwipe removed. */



/* ============================================================
   IDENTITY SETTINGS PAGE (Backend UI)
   ============================================================ */
function zk_identity_menu() {
    add_menu_page('Identity Data', 'Identity', 'manage_options', 'zk-identity', 'zk_identity_page_html', 'dashicons-id', 20);
}
add_action('admin_menu', 'zk_identity_menu');

function zk_identity_page_html() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['zk_identity_nonce']) && wp_verify_nonce($_POST['zk_identity_nonce'], 'zk_save_identity')) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'zk_vital_') === 0 || strpos($key, 'zk_social_') === 0 || strpos($key, 'zk_fav_') === 0 || strpos($key, 'zk_desc_') === 0 || $key === 'zk_skills' || $key === 'zk_profile_img' || $key === 'zk_monologue_content') {
                update_option($key, wp_unslash($value));
            }
        }
        echo '<div class="notice notice-success"><p>Identity data saved successfully!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Identity Settings</h1>
        <p>მართე შენი მონაცემები, სოციალური ქსელები და ბენტო ბოქსების აღწერები აქედან.</p>
        <form method="post" action="">
            <?php wp_nonce_field('zk_save_identity', 'zk_identity_nonce'); ?>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">1. Section Descriptions (Bento Boxes)</h2>
            <table class="form-table">
                <tr><th>Vitals & Connect Desc</th><td><input type="text" name="zk_desc_vitals" value="<?php echo esc_attr(get_option('zk_desc_vitals', "Zurab Kostava's digital ID, where he objectively looks much better than on his actual passport.")); ?>" class="large-text" /></td></tr>
                <tr><th>Skills Desc</th><td><input type="text" name="zk_desc_skills" value="<?php echo esc_attr(get_option('zk_desc_skills', "A brief list of capabilities. This isn't a flex for the portfolio—it's mostly here so he doesn't forget what he can actually do.")); ?>" class="large-text" /></td></tr>
                <tr><th>The Curated Mind Desc</th><td><input type="text" name="zk_desc_favorites" value="<?php echo esc_attr(get_option('zk_desc_favorites', "Cultural influences. The definitive list that proves his exceptional taste in media (and yes, he is quite proud of it).")); ?>" class="large-text" /></td></tr>
                <tr><th>Life & Captures Desc</th><td><input type="text" name="zk_desc_life" value="<?php echo esc_attr(get_option('zk_desc_life', "Behind the scenes. No renders, no code—just real life captured through a lens.")); ?>" class="large-text" /></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">2. Vitals & Profile</h2>
            <table class="form-table">
                <tr><th>Profile Photo URL</th><td><input type="text" name="zk_profile_img" value="<?php echo esc_attr(get_option('zk_profile_img', 'https://via.placeholder.com/150x200')); ?>" class="regular-text" /></td></tr>
                <tr><th>Born</th><td><input type="text" name="zk_vital_born" value="<?php echo esc_attr(get_option('zk_vital_born', 'DD.MM.YYYY')); ?>" class="regular-text" /></td></tr>
                <tr><th>Origin</th><td><input type="text" name="zk_vital_origin" value="<?php echo esc_attr(get_option('zk_vital_origin', 'Ozurgeti, Guria, Georgia')); ?>" class="regular-text" /></td></tr>
                <tr><th>Base</th><td><input type="text" name="zk_vital_base" value="<?php echo esc_attr(get_option('zk_vital_base', 'Tbilisi, Georgia')); ?>" class="regular-text" /></td></tr>
                <tr><th>Email (Primary)</th><td><input type="email" name="zk_vital_email_1" value="<?php echo esc_attr(get_option('zk_vital_email_1', 'zurab@kostavacreative.com')); ?>" class="regular-text" /></td></tr>
                <tr><th>Email (Secondary)</th><td><input type="email" name="zk_vital_email_2" value="<?php echo esc_attr(get_option('zk_vital_email_2', 'zurabkostava1@gmail.com')); ?>" class="regular-text" /></td></tr>
                <tr><th>Studio (HTML)</th><td><input type="text" name="zk_vital_studio" value="<?php echo esc_attr(get_option('zk_vital_studio', 'Creative Lead @ <a href="https://zurabkostava.com" target="_blank">Kostava Creative</a>')); ?>" class="large-text" /></td></tr>
                <tr><th>Position (HTML)</th><td><input type="text" name="zk_vital_position" value="<?php echo esc_attr(get_option('zk_vital_position', 'Web Designer @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>')); ?>" class="large-text" /></td></tr>
                <tr><th>Archetype</th><td><input type="text" name="zk_vital_archetype" value="<?php echo esc_attr(get_option('zk_vital_archetype', 'Composer, Visual Artist, Tech Geek')); ?>" class="large-text" /></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">3. Social Links (თუ ლინკი ცარიელია, იმუშავებს როგორც #)</h2>
            <table class="form-table">
                <tr><th>Instagram URL</th><td><input type="url" name="zk_social_ig" value="<?php echo esc_url(get_option('zk_social_ig', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Facebook URL</th><td><input type="url" name="zk_social_fb" value="<?php echo esc_url(get_option('zk_social_fb', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>X (Twitter) URL</th><td><input type="url" name="zk_social_x" value="<?php echo esc_url(get_option('zk_social_x', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>LinkedIn URL</th><td><input type="url" name="zk_social_linkedin" value="<?php echo esc_url(get_option('zk_social_linkedin', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>YouTube URL</th><td><input type="url" name="zk_social_youtube" value="<?php echo esc_url(get_option('zk_social_youtube', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Spotify URL</th><td><input type="url" name="zk_social_spotify" value="<?php echo esc_url(get_option('zk_social_spotify', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Bandcamp URL</th><td><input type="url" name="zk_social_bandcamp" value="<?php echo esc_url(get_option('zk_social_bandcamp', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Medium URL</th><td><input type="url" name="zk_social_medium" value="<?php echo esc_url(get_option('zk_social_medium', '#')); ?>" class="regular-text" /></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">4. Skills (მძიმით გამოყოფილი)</h2>
            <table class="form-table">
                <tr><th>Skills</th><td><textarea name="zk_skills" rows="3" class="large-text"><?php echo esc_textarea(get_option('zk_skills', 'Music Production, Cinematography & Color Grading, UI/UX Design, Sound Design, Web Technologies, AI Workflows')); ?></textarea></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">5. The Curated Mind (Favorites)</h2>
            <p><em>ფორმატი თითოეული ნივთისთვის (ახალ ხაზზე):</em> <code style="background:#e0e0e0; padding:2px 6px;">სათაური | https://ლინკი.com</code></p>
            <table class="form-table">
                <?php
                $fav_cats = [
                        'zk_fav_cinema' => 'Cinema', 'zk_fav_series' => 'Series', 'zk_fav_books' => 'Books',
                        'zk_fav_writers' => 'Writers', 'zk_fav_directors' => 'Directors', 'zk_fav_actors' => 'Actors',
                        'zk_fav_artists' => 'Musical Artists', 'zk_fav_bands' => 'Bands', 'zk_fav_albums' => 'Albums',
                        'zk_fav_songs' => 'Songs', 'zk_fav_nerds' => 'Tech Nerds', 'zk_fav_scientists' => 'Scientists',
                        'zk_fav_athletes' => 'Athletes', 'zk_fav_models' => 'Models'
                ];
                foreach ($fav_cats as $key => $label) {
                    echo '<tr><th>' . $label . '</th><td><textarea name="' . $key . '" rows="4" class="large-text">' . esc_textarea(get_option($key)) . '</textarea></td></tr>';
                }
                ?>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">6. The Monologue</h2>
            <p>აქ შეგიძლია სრულფასოვანი რედაქტორით ააწყო შენი მონოლოგის ტექსტი. დაამატე აბზაცები, ბმულები ან ციტატები.</p>
            <div style="background:#fff; margin-bottom:20px; max-width:800px;">
                <?php
                $monologue_content = get_option('zk_monologue_content', "<h3>The Creative Process</h3>\n<p>This is where you can dive deep into your story.</p>");
                // ეს იძახებს WordPress-ის სტანდარტულ ვიზუალურ რედაქტორს
                wp_editor($monologue_content, 'zk_monologue_content', array(
                        'textarea_name' => 'zk_monologue_content',
                        'media_buttons' => true,
                        'textarea_rows' => 15,
                        'teeny'         => false
                ));
                ?>
            </div>

            <?php submit_button('Save Identity Data'); ?>
        </form>
    </div>
    <?php
}


/* ============================================================
   ENCROLIB - SECURE WORD SAVE API ENDPOINT
   ============================================================ */
add_action('rest_api_init', function () {
    register_rest_route('zk/v1', '/save-word', array(
            'methods' => 'POST',
            'callback' => 'zk_save_word_endpoint',
            'permission_callback' => function () {
                // მხოლოდ შენ (ადმინისტრატორს) შეგეძლება სიტყვების დამატება
                return current_user_can('manage_options');
            }
    ));
});

function zk_save_word_endpoint($request) {
    $parameters = $request->get_json_params();
    if (!isset($parameters['word'])) {
        return new WP_Error('no_word', 'სიტყვა არ არის.', array('status' => 400));
    }

    $new_word = trim(strtolower($parameters['word']));
    $file_path = WP_CONTENT_DIR . '/Encrolib/words_monster.txt';

    // 🔴 დიაგნოსტიკა: შეამოწმე რას გიბრუნებს ეს $file_path
    if (!file_exists($file_path)) {
        return new WP_Error('no_file', 'ფაილი ვერ მოიძებნა ამ გზაზე: ' . $file_path, array('status' => 404));
    }

    $file_content = file_get_contents($file_path);
    $search_pattern = "\n" . $new_word . "\n";

    if (strpos($file_content, $search_pattern) !== false || strpos($file_content, $new_word . "\n") === 0) {
        return rest_ensure_response(['status' => 'exists', 'word' => $new_word]);
    }

    // ჩაწერა
    $success = file_put_contents($file_path, "\n" . $new_word, FILE_APPEND | LOCK_EX);

    if ($success !== false) {
        // 🟢 აბრუნებს დადასტურებას და ფაილის გზას
        return rest_ensure_response([
                'status' => 'ok',
                'added' => $new_word,
                'path' => $file_path // <--- ეს გამოჩნდება Network ტაბში (Response)
        ]);
    } else {
        return new WP_Error('write_error', 'ფაილი წაკითხვადია, მაგრამ ჩაწერა ვერ მოხერხდა. შეამოწმე CHMOD!', array('status' => 500));
    }
}



/* ============================================================
   📚 BOOKS LIBRARY - CPT & SHORTCODE (V2 - Premium UI)
   ============================================================ */

// 1. მენიუს შექმნა ადმინ-პანელში
function zk_register_books_cpt() {
    $labels = array(
            'name'          => 'Books',
            'singular_name' => 'Book',
            'menu_name'     => 'Books',
            'add_new'       => 'Add New Book',
            'edit_item'     => 'Edit Book',
    );
    $args = array(
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'menu_icon'     => 'dashicons-book-alt',
            'supports'      => array( 'title', 'editor', 'thumbnail' ),
    );
    register_post_type( 'zk_book', $args );
}
add_action( 'init', 'zk_register_books_cpt' );

// 2. დამატებითი ველები წიგნისთვის
function zk_book_add_meta_box() {
    add_meta_box( 'zk_book_details', 'Book Details', 'zk_book_meta_callback', 'zk_book', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_book_add_meta_box' );

function zk_book_meta_callback( $post ) {
    wp_nonce_field( 'zk_book_save_meta', 'zk_book_meta_nonce' );
    $year   = get_post_meta( $post->ID, '_zk_book_year', true );
    $genre  = get_post_meta( $post->ID, '_zk_book_genre', true );
    $author = get_post_meta( $post->ID, '_zk_book_author', true );
    $link   = get_post_meta( $post->ID, '_zk_book_link', true );
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
        <div>
            <label><strong>Release Year</strong> (e.g. 2026)</label><br>
            <input type="text" name="zk_book_year" value="<?php echo esc_attr( $year ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Genre / Format</strong> (e.g. Serialized Sci-Fi)</label><br>
            <input type="text" name="zk_book_genre" value="<?php echo esc_attr( $genre ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Author</strong></label><br>
            <input type="text" name="zk_book_author" value="<?php echo esc_attr( $author ); ?>" placeholder="Zurab Kostava" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Read Link</strong></label><br>
            <input type="url" name="zk_book_link" value="<?php echo esc_url( $link ); ?>" style="width:100%; margin-top:5px;" />
        </div>
    </div>
    <p style="color: #666; margin-top: 15px;"><em>* Set the Book Cover using the "Featured Image" panel on the right. If Author is left empty, it will default to Zurab Kostava.</em></p>
    <?php
}

function zk_book_save_meta( $post_id ) {
    if ( ! isset( $_POST['zk_book_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_book_meta_nonce'], 'zk_book_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    if ( isset( $_POST['zk_book_year'] ) ) update_post_meta( $post_id, '_zk_book_year', sanitize_text_field( $_POST['zk_book_year'] ) );
    if ( isset( $_POST['zk_book_genre'] ) ) update_post_meta( $post_id, '_zk_book_genre', sanitize_text_field( $_POST['zk_book_genre'] ) );
    if ( isset( $_POST['zk_book_author'] ) ) update_post_meta( $post_id, '_zk_book_author', sanitize_text_field( $_POST['zk_book_author'] ) );
    if ( isset( $_POST['zk_book_link'] ) ) update_post_meta( $post_id, '_zk_book_link', sanitize_text_field( $_POST['zk_book_link'] ) );
}
add_action( 'save_post', 'zk_book_save_meta' );

function zk_books_shortcode() {
    $query = new WP_Query( array(
            'post_type'      => 'zk_book',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true
    ) );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">No books published yet.</p>';
    }

    $output = '<div class="zk-books-library">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $id     = get_the_ID();
        $title  = get_the_title();
        $desc   = get_the_content();
        $year   = get_post_meta( $id, '_zk_book_year', true );
        $genre  = get_post_meta( $id, '_zk_book_genre', true );
        $author = get_post_meta( $id, '_zk_book_author', true ) ?: 'Zurab Kostava'; // ცარიელზე ავტომატურად შენს სახელს ჩაწერს
        $link   = get_post_meta( $id, '_zk_book_link', true );

        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( $id, 'large' ) : 'https://via.placeholder.com/400x600?text=No+Cover';

        $output .= '<div class="zk-book-card">';

        // --- 3D Cover ---
        $output .= '<div class="zk-book-visual">';
        $output .= '<div class="zk-book-aura" style="background-image: url(' . esc_url( $img_url ) . ');"></div>';
        $output .= '<div class="zk-book-cover">';
        $output .= '<img src="' . esc_url( $img_url ) . '" loading="lazy" decoding="async" alt="Book Cover" class="zk-book-img">';
        $output .= '<div class="zk-book-spine"></div>';
        $output .= '</div></div>';

        // --- Info Section ---
        $output .= '<div class="zk-book-info">';

        // Meta Bar
        $output .= '<div class="zk-book-meta-bar">';
        if ( $genre ) {
            $output .= '<span class="zk-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>' . esc_html( $genre ) . '</span>';
        }
        if ( $year ) {
            $output .= '<span class="zk-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>' . esc_html( $year ) . '</span>';
        }
        $output .= '</div>'; // end meta bar

        $output .= '<h3 class="zk-book-title">' . esc_html( $title ) . '</h3>';
        if ( $author ) {
            $output .= '<div class="zk-book-author">by <span>' . esc_html( $author ) . '</span></div>';
        }
        $output .= '<div class="zk-book-desc">' . wpautop( $desc ) . '</div>';

        // Actions
        $output .= '<div class="zk-book-actions">';
        if ( $link ) {
            $output .= '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener" class="zk-read-btn">';
            $output .= '<span>Read Experiment</span>';
            $output .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';
            $output .= '</a>';
        }
        $output .= '</div>'; // end actions

        $output .= '</div>'; // end info
        $output .= '</div>'; // end card
    }

    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}
add_shortcode( 'zk_books', 'zk_books_shortcode' );

/* ============================================================
   TOOLS / PROJECTS HUB
   ============================================================ */
function zk_register_tools_cpt() {
    register_post_type( 'zk_tool', array(
        'labels' => array(
            'name'          => 'Tools / Projects',
            'singular_name' => 'Tool',
            'add_new_item'  => 'Add New Tool',
            'edit_item'     => 'Edit Tool',
        ),
        'public'      => true,
        'has_archive' => false,
        'menu_icon'   => 'dashicons-hammer',
        'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
    ) );
}
add_action( 'init', 'zk_register_tools_cpt' );

function zk_tool_meta_boxes() {
    add_meta_box( 'zk_tool_meta', 'Tool Details', 'zk_tool_meta_callback', 'zk_tool', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_tool_meta_boxes' );

function zk_tool_meta_callback( $post ) {
    $link   = get_post_meta( $post->ID, '_zk_tool_link', true );
    $status = get_post_meta( $post->ID, '_zk_tool_status', true );
    ?>
    <p>
        <label for="zk_tool_link"><strong>Project Link:</strong></label><br>
        <input type="text" id="zk_tool_link" name="zk_tool_link" value="<?php echo esc_attr( $link ); ?>" style="width:100%;" placeholder="e.g. https://encrolib.com" />
    </p>
    <p>
        <label for="zk_tool_status"><strong>Status:</strong></label><br>
        <select id="zk_tool_status" name="zk_tool_status">
            <option value="Live" <?php selected($status, 'Live'); ?>>Live</option>
            <option value="Beta" <?php selected($status, 'Beta'); ?>>Beta</option>
            <option value="WIP" <?php selected($status, 'WIP'); ?>>Work in Progress</option>
            <option value="Archived" <?php selected($status, 'Archived'); ?>>Archived</option>
        </select>
    </p>
    <?php
}

function zk_tool_save_meta( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( isset( $_POST['zk_tool_link'] ) ) update_post_meta( $post_id, '_zk_tool_link', sanitize_text_field( $_POST['zk_tool_link'] ) );
    if ( isset( $_POST['zk_tool_status'] ) ) update_post_meta( $post_id, '_zk_tool_status', sanitize_text_field( $_POST['zk_tool_status'] ) );
}
add_action( 'save_post', 'zk_tool_save_meta' );

function zk_tools_shortcode() {
    $query = new WP_Query( array(
            'post_type'      => 'zk_tool',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true
    ) );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">No tools available yet.</p>';
    }

    $output = '<div class="zk-tools-grid">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id   = get_the_ID();
        $title     = get_the_title();
        $excerpt   = get_the_excerpt();
        $link      = get_post_meta( $post_id, '_zk_tool_link', true );
        $status    = get_post_meta( $post_id, '_zk_tool_status', true ) ?: 'Live';
        $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
        
        $card_tag = $link ? 'a' : 'div';
        $href     = $link ? ' href="' . esc_url( $link ) . '" target="_blank" rel="noopener"' : '';
        $status_class = strtolower(str_replace(' ', '-', $status));

        $output .= '<' . $card_tag . $href . ' class="zk-tool-card">';
        
        if ( $thumb_url ) {
            $output .= '<div class="zk-tool-image" style="background-image: url(\'' . esc_url( $thumb_url ) . '\');"></div>';
        } else {
            $output .= '<div class="zk-tool-image zk-tool-image-empty"></div>';
        }

        $output .= '<div class="zk-tool-content">';
        $output .= '<div class="zk-tool-header">';
        $output .= '<h3 class="zk-tool-title">' . esc_html( $title ) . '</h3>';
        $output .= '<span class="zk-tool-status status-' . esc_attr( $status_class ) . '">' . esc_html( $status ) . '</span>';
        $output .= '</div>';
        
        if ( $excerpt ) {
            $output .= '<p class="zk-tool-excerpt">' . esc_html( $excerpt ) . '</p>';
        }

        if ( $link ) {
            $output .= '<div class="zk-tool-footer"><span class="zk-tool-link-text">Visit Project</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>';
        }

        $output .= '</div>';
        $output .= '</' . $card_tag . '>';
    }

    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}
add_shortcode( 'zk_tools', 'zk_tools_shortcode' );


/* ============================================================
   VISUAL HUB
   ============================================================ */
function zk_visual_hub_shortcode() {
    $items = array(
        array(
            'title' => 'Photography',
            'desc'  => 'Capturing moments and light',
            'link'  => home_url('/photography/'),
            'class' => 'visual-photo'
        ),
        array(
            'title' => 'Video',
            'desc'  => 'Motion pictures and edits',
            'link'  => home_url('/video/'),
            'class' => 'visual-video'
        ),
        array(
            'title' => 'Graphic Design',
            'desc'  => 'Visual communication and UI',
            'link'  => home_url('/graphic/'),
            'class' => 'visual-graphic'
        ),
        array(
            'title' => 'Paint',
            'desc'  => 'Digital and traditional art',
            'link'  => home_url('/paint/'),
            'class' => 'visual-paint'
        )
    );

    $output = '<div class="zk-visual-hub">';
    foreach ( $items as $item ) {
        $output .= '<a href="' . esc_url( $item['link'] ) . '" class="zk-visual-card ' . esc_attr( $item['class'] ) . '">';
        $output .= '<div class="zk-visual-bg"></div>';
        $output .= '<div class="zk-visual-content">';
        $output .= '<h2 class="zk-visual-title">' . esc_html( $item['title'] ) . '</h2>';
        $output .= '<p class="zk-visual-desc">' . esc_html( $item['desc'] ) . '</p>';
        $output .= '<div class="zk-visual-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>';
        $output .= '</div>';
        $output .= '</a>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode( 'zk_visual_hub', 'zk_visual_hub_shortcode' );

/* ============================================================
   CUSTOM SEO REDIRECTS (Site Architecture Migration)
   ============================================================ */
function zk_custom_seo_redirects() {
    // Execute extremely fast, bail if in WP admin panel
    if ( is_admin() ) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'];
    // Convert path to lowercase to match exactly regardless of casing (e.g. /Nocturnes/)
    $path = strtolower( rtrim( parse_url( $request_uri, PHP_URL_PATH ), '/' ) );

    // Strip /ka prefix for exact matches and dynamic routes to avoid double redirects
    if ( strpos( $path, '/ka/' ) === 0 || $path === '/ka' ) {
        $clean_path = substr( $path, 3 );
        if ( $clean_path === false || $clean_path === '' ) {
            $clean_path = '/';
        }
    } else {
        $clean_path = $path;
    }

    // 1. EXACT MATCH REDIRECTS
    $exact_matches = array(
        '/aubades'     => '/blog/raw/aubades/',
        '/nocturnes'   => '/blog/raw/nocturnes/',
        '/gallery'     => '/about/',
        '/photography' => '/visual/photography/',
        '/video'       => '/visual/video/',
        '/graphic'     => '/visual/graphic/',
        '/paint'       => '/visual/paint/'
    );

    if ( array_key_exists( $clean_path, $exact_matches ) ) {
        wp_redirect( home_url( $exact_matches[ $clean_path ] ), 301 );
        exit;
    }

    // 2. DYNAMIC REGEX REDIRECTS
    if ( preg_match( '#^/nocturne-([^/]+)$#', $clean_path, $matches ) ) {
        wp_redirect( home_url( '/blog/raw/nocturnes/nocturne-' . $matches[1] . '/' ), 301 );
        exit;
    }

    if ( preg_match( '#^/aubade-([^/]+)$#', $clean_path, $matches ) ) {
        wp_redirect( home_url( '/blog/raw/aubades/aubade-' . $matches[1] . '/' ), 301 );
        exit;
    }

    // 3. GLOBAL /ka/ FALLBACK
    if ( strpos( $path, '/ka/' ) === 0 || $path === '/ka' ) {
        // We replace ^/ka at the start of $request_uri so we preserve query params
        $new_uri = preg_replace( '#^/ka(?=/|$)#', '', $request_uri );
        if ( $new_uri === '' ) {
            $new_uri = '/';
        }
        wp_redirect( home_url( $new_uri ), 301 );
        exit;
    }
}
// Using 'init' instead of 'template_redirect' so it fires before WP query and 404 logic
add_action( 'init', 'zk_custom_seo_redirects' );

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 1)
   ============================================================ */

// 1. Add Custom Meta Box
function zk_seo_add_meta_box() {
    $screens = array( 'post', 'page', 'zk_book' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'zk_seo_meta_box',           // Unique ID
            'ZK Custom SEO Settings',     // Box title
            'zk_seo_meta_box_html',      // Content callback
            $screen,                     // Post type
            'normal',                    // Context
            'high'                       // Priority
        );
    }
}
add_action( 'add_meta_boxes', 'zk_seo_add_meta_box' );

function zk_seo_meta_box_html( $post ) {
    wp_nonce_field( 'zk_seo_save_meta', 'zk_seo_meta_nonce' );
    $seo_title = get_post_meta( $post->ID, '_zk_seo_title', true );
    $seo_desc  = get_post_meta( $post->ID, '_zk_seo_description', true );
    ?>
    <div style="padding: 10px 0;">
        <label for="zk_seo_title" style="display:block; font-weight:bold; margin-bottom:5px;">SEO Title</label>
        <input type="text" id="zk_seo_title" name="zk_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" style="width:100%; max-width:600px; margin-bottom:15px;" placeholder="Leave empty to use post title..." />
        
        <label for="zk_seo_description" style="display:block; font-weight:bold; margin-bottom:5px;">SEO Description</label>
        <textarea id="zk_seo_description" name="zk_seo_description" rows="3" style="width:100%; max-width:600px;" placeholder="Leave empty to use post excerpt or default description..."><?php echo esc_textarea( $seo_desc ); ?></textarea>
    </div>
    <?php
}

// 2. Save Meta Box Data
function zk_seo_save_meta( $post_id ) {
    if ( ! isset( $_POST['zk_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_seo_meta_nonce'], 'zk_seo_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['zk_seo_title'] ) ) {
        update_post_meta( $post_id, '_zk_seo_title', sanitize_text_field( $_POST['zk_seo_title'] ) );
    }
    if ( isset( $_POST['zk_seo_description'] ) ) {
        update_post_meta( $post_id, '_zk_seo_description', sanitize_textarea_field( $_POST['zk_seo_description'] ) );
    }
}
add_action( 'save_post', 'zk_seo_save_meta' );

// 3. Prevent WP Default Title Output & Inject Custom SEO Tags
// Ensure WP doesn't output its own <title> if theme supports it
remove_action( 'wp_head', '_wp_render_title_tag', 1 );

function zk_render_seo_meta() {
    // Determine context
    $is_single = is_single() || is_page();
    $obj_id = get_queried_object_id();
    
    // Default Fallbacks
    $site_name = get_bloginfo( 'name' );
    $site_desc = get_bloginfo( 'description' );
    
    $title = $site_name . ( $site_desc ? ' — ' . $site_desc : '' );
    $desc = $site_desc;
    $url = home_url( $_SERVER['REQUEST_URI'] );
    $type = 'website';
    $img = get_option( 'zk_profile_img', '' ); // Default fallback image from Identity Settings

    // Override for single posts/pages
    if ( $is_single ) {
        $type = 'article';
        
        // Fetch custom SEO meta
        $custom_title = get_post_meta( $obj_id, '_zk_seo_title', true );
        $custom_desc  = get_post_meta( $obj_id, '_zk_seo_description', true );
        
        // Title logic
        if ( ! empty( $custom_title ) ) {
            $title = $custom_title;
        } else {
            $title = get_the_title( $obj_id ) . ' — ' . $site_name;
        }
        
        // Description logic
        if ( ! empty( $custom_desc ) ) {
            $desc = $custom_desc;
        } elseif ( has_excerpt( $obj_id ) ) {
            $desc = wp_strip_all_tags( get_the_excerpt( $obj_id ) );
        } else {
            $post_content = get_post( $obj_id )->post_content;
            $desc = wp_trim_words( wp_strip_all_tags( $post_content ), 30, '...' );
        }
        
        // Image logic
        if ( has_post_thumbnail( $obj_id ) ) {
            $img = get_the_post_thumbnail_url( $obj_id, 'large' );
        }
    } elseif ( is_archive() ) {
        if ( is_category() ) {
            $title = single_cat_title( '', false ) . ' — ' . $site_name;
            $desc = wp_strip_all_tags( category_description() ) ?: $desc;
        } elseif ( is_tag() ) {
            $title = single_tag_title( '', false ) . ' — ' . $site_name;
            $desc = wp_strip_all_tags( tag_description() ) ?: $desc;
        }
    } elseif ( is_search() ) {
        $title = 'Search Results for "' . get_search_query() . '" — ' . $site_name;
    } elseif ( is_404() ) {
        $title = '404 Not Found — ' . $site_name;
    }

    // Clean up title and description to prevent HTML breaks
    $title = esc_attr( wp_strip_all_tags( $title ) );
    $desc = esc_attr( wp_strip_all_tags( $desc ) );

    // Output Tags
    echo "\n<!-- ZK Custom SEO Engine -->\n";
    echo "<title>{$title}</title>\n";
    if ( ! empty( $desc ) ) {
        echo "<meta name=\"description\" content=\"{$desc}\" />\n";
    }
    
    // Canonical URL
    echo "<link rel=\"canonical\" href=\"" . esc_url( $url ) . "\" />\n";
    
    // Open Graph
    echo "<meta property=\"og:title\" content=\"{$title}\" />\n";
    if ( ! empty( $desc ) ) echo "<meta property=\"og:description\" content=\"{$desc}\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url( $url ) . "\" />\n";
    echo "<meta property=\"og:site_name\" content=\"" . esc_attr( $site_name ) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"{$type}\" />\n";
    if ( ! empty( $img ) ) echo "<meta property=\"og:image\" content=\"" . esc_url( $img ) . "\" />\n";
    
    // Twitter Cards
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    echo "<meta name=\"twitter:title\" content=\"{$title}\" />\n";
    if ( ! empty( $desc ) ) echo "<meta name=\"twitter:description\" content=\"{$desc}\" />\n";
    if ( ! empty( $img ) ) echo "<meta name=\"twitter:image\" content=\"" . esc_url( $img ) . "\" />\n";
    echo "<!-- /ZK Custom SEO Engine -->\n";
}
add_action( 'wp_head', 'zk_render_seo_meta', 1 );

// 4. JSON-LD Schema Generator (AI & Google SEO)
function zk_render_json_ld_schema() {
    $site_name = get_bloginfo( 'name' );
    $site_url = home_url( '/' );
    $logo_url = get_option( 'zk_profile_img', '' );
    
    // Build Social Links array
    $social_keys = ['zk_social_ig', 'zk_social_fb', 'zk_social_x', 'zk_social_linkedin', 'zk_social_youtube', 'zk_social_spotify', 'zk_social_bandcamp', 'zk_social_medium'];
    $same_as = [];
    foreach ($social_keys as $key) {
        $url = esc_url(get_option($key, ''));
        if (!empty($url) && $url !== '#' && $url !== 'http://#' && $url !== 'https://#') {
            $same_as[] = $url;
        }
    }

    $schema = [];

    // Base Person Schema (always outputting the author entity)
    $person_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => 'Zurab Kostava',
        'alternateName' => ['ზურაბ კოსტავა', 'Zurab Kostava', 'Zurab', 'Kostava', 'Zura Kostava'],
        'url' => $site_url,
        'jobTitle' => wp_strip_all_tags(get_option('zk_vital_position', 'Creative Lead')),
        'knowsAbout' => ['Web Design', 'UI/UX', 'Science Fiction', 'Music Production', 'Literature', 'Art Direction'],
        'nationality' => [
            '@type' => 'Country',
            'name' => 'Georgia'
        ],
        'image' => $logo_url,
        'sameAs' => $same_as
    ];

    if ( is_front_page() || is_home() ) {
        // Output Person + WebSite on home page
        $schema[] = $person_schema;
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_name,
            'url' => $site_url,
            'publisher' => [
                '@id' => $site_url . '#person'
            ]
        ];
        $person_schema['@id'] = $site_url . '#person'; // link them
        $schema[0] = $person_schema;

    } elseif ( is_singular( 'post' ) || is_page() ) {
        global $post;
        $desc = get_post_meta( $post->ID, '_zk_seo_description', true ) ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
        
        $article_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'description' => esc_attr( $desc ),
            'datePublished' => get_the_date( 'c' ),
            'dateModified' => get_the_modified_date( 'c' ),
            'author' => [
                '@type' => 'Person',
                'name' => 'Zurab Kostava',
                'url' => $site_url
            ],
            'publisher' => [
                '@type' => 'Person',
                'name' => 'Zurab Kostava',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $logo_url
                ]
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink()
            ],
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['h1', 'h2', 'p']
            ]
        ];

        // Extract WP Tags and Categories for Keywords
        $keywords = [];
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach($tags as $tag) { $keywords[] = $tag->name; }
        }
        $categories = get_the_category($post->ID);
        if ($categories) {
            foreach($categories as $cat) { $keywords[] = $cat->name; }
        }
        if (!empty($keywords)) {
            $article_schema['keywords'] = implode(', ', $keywords);
        }
        
        if ( has_post_thumbnail() ) {
            $article_schema['image'] = get_the_post_thumbnail_url( $post->ID, 'full' );
        }
        $schema[] = $article_schema;

    } elseif ( is_singular( 'zk_book' ) ) {
        global $post;
        $year   = get_post_meta( $post->ID, '_zk_book_year', true );
        $genre  = get_post_meta( $post->ID, '_zk_book_genre', true );
        $author = get_post_meta( $post->ID, '_zk_book_author', true ) ?: 'Zurab Kostava';
        $desc   = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '' );
        
        $book_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => get_the_title(),
            'author' => [
                '@type' => 'Person',
                'name' => $author
            ],
            'datePublished' => $year,
            'bookFormat' => 'https://schema.org/EBook',
            'description' => esc_attr( $desc ),
            'url' => get_permalink()
        ];
        
        if ( ! empty( $genre ) ) {
            $book_schema['genre'] = esc_attr( $genre );
            $book_schema['keywords'] = esc_attr( $genre ) . ', ' . get_the_title() . ', Book, Zurab Kostava, ზურაბ კოსტავა';
        }
        if ( has_post_thumbnail() ) {
            $book_schema['image'] = get_the_post_thumbnail_url( $post->ID, 'full' );
        }
        $schema[] = $book_schema;

    } else {
        // Fallback for Archives, Tags, Categories, and Custom Routes (like /music/)
        $schema[] = $person_schema;
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title() ?: $site_name,
            'url' => home_url( $_SERVER['REQUEST_URI'] ),
            'publisher' => [
                '@id' => $site_url . '#person'
            ]
        ];
        $person_schema['@id'] = $site_url . '#person';
        $schema[0] = $person_schema;
    }

    // Add BreadcrumbList Schema if not on home page
    if ( ! is_front_page() && ! is_home() ) {
        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $site_url
                ]
            ]
        ];

        if ( is_singular( 'post' ) ) {
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Blog',
                'item' => home_url( '/blog/' )
            ];
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => get_the_title(),
                'item' => get_permalink()
            ];
        } elseif ( is_singular( 'zk_book' ) ) {
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Books',
                'item' => home_url( '/books/' )
            ];
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => get_the_title(),
                'item' => get_permalink()
            ];
        } elseif ( is_page() ) {
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => get_the_title(),
                'item' => get_permalink()
            ];
        } else {
            // General archive / custom route
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => get_the_title() ?: 'Archive',
                'item' => home_url( $_SERVER['REQUEST_URI'] )
            ];
        }
        $schema[] = $breadcrumbs;
    }

    if ( ! empty( $schema ) ) {
        echo "\n<!-- ZK JSON-LD Schema Engine -->\n";
        echo "<script type=\"application/ld+json\">\n";
        echo json_encode( count( $schema ) === 1 ? $schema[0] : $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
        echo "<!-- /ZK JSON-LD Schema Engine -->\n";
    }
}
add_action( 'wp_head', 'zk_render_json_ld_schema', 2 );

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 3 - Static Architecture)
   ============================================================ */

// 1. Extreme Head Cleanup (Zero Bloat)
function zk_clean_head() {
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
    remove_action('template_redirect', 'rest_output_link_header', 11, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
}
add_action('init', 'zk_clean_head');

// Disable native WP sitemap
add_filter('wp_sitemaps_enabled', '__return_false');

// 2. Dynamic SEO File Generators (Bypasses File Permissions and NGINX rewrites)
function zk_generate_sitemap_string() {
    $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $date = date('c');
    $custom_routes = ['/', '/music/', '/encrolib/', '/contact/'];
    foreach ($custom_routes as $route) {
        $sitemap_content .= "  <url>\n";
        $sitemap_content .= "    <loc>" . esc_url(home_url($route)) . "</loc>\n";
        $sitemap_content .= "    <lastmod>{$date}</lastmod>\n";
        $sitemap_content .= "    <changefreq>weekly</changefreq>\n";
        $sitemap_content .= "    <priority>" . ($route === '/' ? '1.0' : '0.8') . "</priority>\n";
        $sitemap_content .= "  </url>\n";
    }

    $query = new WP_Query([
        'post_type' => ['post', 'page', 'zk_book'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ]);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $sitemap_content .= "  <url>\n";
            $sitemap_content .= "    <loc>" . esc_url(get_permalink()) . "</loc>\n";
            $sitemap_content .= "    <lastmod>" . get_the_modified_date('c') . "</lastmod>\n";
            $sitemap_content .= "    <changefreq>monthly</changefreq>\n";
            $sitemap_content .= "    <priority>0.6</priority>\n";
            $sitemap_content .= "  </url>\n";
        }
        wp_reset_postdata();
    }
    $sitemap_content .= '</urlset>';
    return $sitemap_content;
}

function zk_generate_aitxt_string() {
    $name = "Zurab Kostava";
    $position = get_option('zk_vital_position', 'Creative Lead');
    $about = wp_strip_all_tags(get_option('zk_vital_about', ''));
    
    $ai_content = "# $name - $position\n\n";
    $ai_content .= "## About\n$about\n\n";
    
    $ai_content .= "## Links\n";
    $social_keys = ['zk_social_ig', 'zk_social_fb', 'zk_social_x', 'zk_social_linkedin', 'zk_social_youtube', 'zk_social_spotify', 'zk_social_bandcamp', 'zk_social_medium'];
    foreach ($social_keys as $key) {
        $url = esc_url(get_option($key, ''));
        if (!empty($url) && $url !== '#' && strpos($url, 'http') === 0) {
            $ai_content .= "- $url\n";
        }
    }
    
    $ai_content .= "\n## Latest Works / Books\n";
    $query = new WP_Query([
        'post_type' => 'zk_book',
        'post_status' => 'publish',
        'posts_per_page' => 10,
    ]);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $year = get_post_meta(get_the_ID(), '_zk_book_year', true);
            $genre = get_post_meta(get_the_ID(), '_zk_book_genre', true);
            $ai_content .= "- " . get_the_title() . " ($year) [$genre]\n";
        }
        wp_reset_postdata();
    }
    
    $ai_content .= "\n## Contact\n";
    $ai_content .= "- Email: " . get_option('admin_email') . "\n";
    $ai_content .= "- Website: " . home_url('/') . "\n";
    return $ai_content;
}

function zk_serve_dynamic_seo_files() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    if ( preg_match( '/\/wp-sitemap\.xml$/i', $uri ) ) {
        header('Content-Type: text/xml; charset=utf-8');
        echo zk_generate_sitemap_string();
        exit;
    }
    
    if ( preg_match( '/\/ai\.txt$/i', $uri ) ) {
        header('Content-Type: text/plain; charset=utf-8');
        echo zk_generate_aitxt_string();
        exit;
    }
}
add_action( 'template_redirect', 'zk_serve_dynamic_seo_files', 1 );

// 3. Override robots.txt
function zk_custom_robots_txt($output, $public) {
    $output .= "\nUser-agent: *\n";
    $output .= "Allow: /\n";
    $output .= "\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
    return $output;
}
add_filter('robots_txt', 'zk_custom_robots_txt', 10, 2);

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 4 - Image SEO)
   ============================================================ */

// 1. Image Auto Alt-Text Enforcer
function zk_auto_image_alt_text( $attributes, $attachment ) {
    if ( empty( $attributes['alt'] ) ) {
        // Fallback to attachment title or post title
        $alt = get_the_title( $attachment->post_parent );
        if ( empty( $alt ) ) {
            $alt = get_the_title( $attachment->ID );
        }
        $attributes['alt'] = esc_attr( $alt );
    }
    return $attributes;
}
add_filter( 'wp_get_attachment_image_attributes', 'zk_auto_image_alt_text', 10, 2 );

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 5 - GEO & Advanced SEO)
   ============================================================ */

// 1. GEO Meta Tags (Robots Directives & AI Summary)
function zk_render_geo_meta_tags() {
    if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
        return;
    }
    
    // Explicit permission for SGE & AI Bots to use max snippets
    echo "<meta name=\"robots\" content=\"max-snippet:-1, max-image-preview:large, max-video-preview:-1\" />\n";

    // AI Summary (abstract)
    if ( is_singular() ) {
        global $post;
        $ai_summary = get_post_meta( $post->ID, '_zk_geo_ai_summary', true );
        if ( ! empty( $ai_summary ) ) {
            echo '<meta name="abstract" content="' . esc_attr( $ai_summary ) . '" />' . "\n";
        }
    }
}
add_action( 'wp_head', 'zk_render_geo_meta_tags', 1 );

// 2. Auto-Generated Table of Contents (TOC)
function zk_auto_toc_generator( $content ) {
    if ( ! is_singular( [ 'post', 'page', 'zk_book' ] ) || empty( $content ) ) {
        return $content;
    }

    // Find all <h2> tags
    preg_match_all( '/<h2(.*?)>(.*?)<\/h2>/i', $content, $matches );
    
    if ( ! empty( $matches[2] ) && count( $matches[2] ) > 1 ) { // Only add TOC if more than 1 heading
        $toc = '<div class="zk-seo-toc" style="background:#111; padding:20px; border-radius:8px; margin-bottom:30px; border: 1px solid #333;">';
        $toc .= '<strong style="display:block; margin-bottom:10px; font-size:18px;">Table of Contents</strong>';
        $toc .= '<ul style="margin:0; padding-left:20px; list-style-type:decimal;">';
        
        foreach ( $matches[2] as $i => $heading ) {
            $clean_text = wp_strip_all_tags( $heading );
            $slug = sanitize_title( $clean_text );
            if ( empty( $slug ) ) { $slug = 'section-' . $i; }
            
            // Add ID to the original heading
            $original_h2 = $matches[0][$i];
            $new_h2 = '<h2 id="' . $slug . '"' . $matches[1][$i] . '>' . $matches[2][$i] . '</h2>';
            $content = str_replace( $original_h2, $new_h2, $content );
            
            // Add to TOC list
            $toc .= '<li style="margin-bottom:5px;"><a href="#' . $slug . '" style="color:#00e6ff; text-decoration:none;">' . esc_html( $clean_text ) . '</a></li>';
        }
        
        $toc .= '</ul></div>';
        
        // Insert TOC before the first <h2>
        $content = preg_replace( '/<h2/', $toc . '<h2', $content, 1 );
    }

    return $content;
}
add_filter( 'the_content', 'zk_auto_toc_generator' );

// 3. GEO Meta Box (AI Summary & FAQ)
function zk_add_geo_meta_box() {
    $screens = [ 'post', 'page', 'zk_book' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'zk_geo_meta_box',
            'ZK GEO Settings (AI & FAQ)',
            'zk_render_geo_meta_box',
            $screen,
            'normal',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'zk_add_geo_meta_box' );

function zk_render_geo_meta_box( $post ) {
    wp_nonce_field( 'zk_geo_save_meta_box_data', 'zk_geo_meta_box_nonce' );
    $ai_summary = get_post_meta( $post->ID, '_zk_geo_ai_summary', true );
    $faq_text   = get_post_meta( $post->ID, '_zk_geo_faq', true );
    ?>
    <p>
        <label for="zk_geo_ai_summary"><strong>AI Summary (Abstract):</strong> (Tell ChatGPT exactly how to summarize this page)</label><br />
        <textarea id="zk_geo_ai_summary" name="zk_geo_ai_summary" rows="3" style="width:100%; margin-top:5px;"><?php echo esc_textarea( $ai_summary ); ?></textarea>
    </p>
    <p style="margin-top:20px;">
        <label for="zk_geo_faq"><strong>FAQ Schema Generator:</strong> (For Google "People Also Ask")</label><br />
        <span class="description" style="display:block; margin-bottom:5px;">Format exactly like this:<br/>Q: What is Beta?<br/>A: It is a book.<br/><br/>Q: Next question?<br/>A: Next answer.</span>
        <textarea id="zk_geo_faq" name="zk_geo_faq" rows="8" style="width:100%; margin-top:5px; font-family:monospace;"><?php echo esc_textarea( $faq_text ); ?></textarea>
    </p>
    <?php
}

function zk_save_geo_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['zk_geo_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['zk_geo_meta_box_nonce'], 'zk_geo_save_meta_box_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['zk_geo_ai_summary'] ) ) {
        update_post_meta( $post_id, '_zk_geo_ai_summary', sanitize_textarea_field( $_POST['zk_geo_ai_summary'] ) );
    }
    if ( isset( $_POST['zk_geo_faq'] ) ) {
        update_post_meta( $post_id, '_zk_geo_faq', sanitize_textarea_field( $_POST['zk_geo_faq'] ) );
    }
}
add_action( 'save_post', 'zk_save_geo_meta_box_data' );

// 4. Inject FAQ JSON-LD Schema
function zk_inject_faq_schema() {
    if ( ! is_singular() ) return;
    global $post;
    $faq_text = get_post_meta( $post->ID, '_zk_geo_faq', true );
    if ( empty( trim( $faq_text ) ) ) return;

    $blocks = explode( "\n\n", str_replace( "\r", "", $faq_text ) );
    $mainEntity = [];

    foreach ( $blocks as $block ) {
        $lines = explode( "\n", trim( $block ) );
        $q = ''; $a = '';
        foreach ( $lines as $line ) {
            if ( str_starts_with( $line, 'Q:' ) ) { $q = trim( substr( $line, 2 ) ); }
            elseif ( str_starts_with( $line, 'A:' ) ) { $a = trim( substr( $line, 2 ) ); }
        }
        if ( ! empty( $q ) && ! empty( $a ) ) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => esc_attr( $q ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => esc_attr( $a )
                ]
            ];
        }
    }

    if ( ! empty( $mainEntity ) ) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity
        ];
        echo "\n<!-- ZK FAQ Schema Engine -->\n";
        echo "<script type=\"application/ld+json\">\n";
        echo json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
        echo "<!-- /ZK FAQ Schema Engine -->\n";
    }
}
add_action( 'wp_head', 'zk_inject_faq_schema', 3 );

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 7 - Ultimate Technical & AEO)
   ============================================================ */

// 1. Hreflang Tags (Preparation for multi-language)
function zk_render_hreflang_tags() {
    if ( is_admin() || is_feed() || is_robots() ) return;
    $url = is_singular() ? get_permalink() : home_url( $_SERVER['REQUEST_URI'] );
    echo "\n<!-- ZK Hreflang Engine -->\n";
    echo '<link rel="alternate" hreflang="en-US" href="' . esc_url( $url ) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '" />' . "\n";
}
add_action( 'wp_head', 'zk_render_hreflang_tags', 1 );

// 2. Automated Internal Linking Engine (SEO Powerhouse)
function zk_auto_internal_linker( $content ) {
    if ( is_admin() || ! is_singular( 'post' ) || empty( $content ) ) {
        return $content;
    }

    // Get all books to auto-link
    $books = get_posts([
        'post_type' => 'zk_book',
        'numberposts' => -1,
        'post_status' => 'publish'
    ]);

    if ( empty( $books ) ) return $content;

    foreach ( $books as $book ) {
        $title = esc_html( $book->post_title );
        $url = get_permalink( $book->ID );
        
        // Regex explanation:
        // \b : word boundary
        // (?![^<]*>) : negative lookahead ensuring we are not inside an HTML tag
        // Limits replacement to 1 per book title
        $pattern = '/\b(' . preg_quote( $title, '/' ) . ')\b(?![^<]*>)/i';
        $replacement = '<a href="' . esc_url( $url ) . '" class="zk-auto-link" title="Read more about ' . esc_attr( $title ) . '">$1</a>';
        
        $content = preg_replace( $pattern, $replacement, $content, 1 ); 
    }

    return $content;
}
add_filter( 'the_content', 'zk_auto_internal_linker', 20 );

/* ============================================================
   ZK ADMIN UX ENHANCEMENTS
   ============================================================ */
function zk_make_tags_hierarchical() {
    global $wp_taxonomies;
    if ( isset( $wp_taxonomies['post_tag'] ) ) {
        $wp_taxonomies['post_tag']->hierarchical = true;
        $wp_taxonomies['post_tag']->show_ui = true;
        $wp_taxonomies['post_tag']->meta_box_cb = 'post_categories_meta_box';
    }
}
add_action( 'init', 'zk_make_tags_hierarchical' );

/* ============================================================
   ZK QUICK EDIT ADVANCED TAG ADDER
   ============================================================ */
// 1. Process AJAX Request
add_action( 'wp_ajax_zk_add_quick_tag', 'zk_ajax_add_quick_tag' );
function zk_ajax_add_quick_tag() {
    check_ajax_referer( 'zk_add_tag_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $name = isset( $_POST['tag_name'] ) ? sanitize_text_field( $_POST['tag_name'] ) : '';
    $slug = isset( $_POST['tag_slug'] ) ? sanitize_text_field( $_POST['tag_slug'] ) : '';
    $desc = isset( $_POST['tag_desc'] ) ? sanitize_textarea_field( $_POST['tag_desc'] ) : '';

    if ( empty( $name ) ) {
        wp_send_json_error( 'Tag name is required.' );
    }

    $args = [];
    if ( ! empty( $slug ) ) $args['slug'] = $slug;
    if ( ! empty( $desc ) ) $args['description'] = $desc;

    $term = wp_insert_term( $name, 'post_tag', $args );

    if ( is_wp_error( $term ) ) {
        wp_send_json_error( $term->get_error_message() );
    }

    $term_id = $term['term_id'];
    $term_obj = get_term( $term_id, 'post_tag' );

    wp_send_json_success( [
        'term_id' => $term_id,
        'name'    => $term_obj->name
    ] );
}

// 2. Inject JS into admin footer
add_action( 'admin_print_footer_scripts', 'zk_quick_edit_add_tag_js' );
function zk_quick_edit_add_tag_js() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'edit' || $screen->post_type !== 'post' ) return;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Inject the HTML into the hidden Quick Edit template
        var formHtml = '<div class="zk-custom-tag-adder" style="width: 100%; border-top: 1px solid #444; margin-top: 15px; padding-top: 15px; clear: both;">' +
            '<span class="title" style="font-weight:600; margin-bottom:8px; display:block;">Add New Tag</span>' +
            '<div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">' +
                '<label style="flex: 1; min-width: 100px; margin:0;">' +
                    '<span class="title" style="margin-bottom:4px;font-size:11px;line-height:1;">Name <span style="color:#d63638">*</span></span>' +
                    '<input type="text" class="zk-new-tag-name" value="" autocomplete="off" style="width:100%; padding: 0 8px; line-height: 2;">' +
                '</label>' +
                '<label style="flex: 1; min-width: 100px; margin:0;">' +
                    '<span class="title" style="margin-bottom:4px;font-size:11px;line-height:1;">Slug (opt)</span>' +
                    '<input type="text" class="zk-new-tag-slug" value="" autocomplete="off" style="width:100%; padding: 0 8px; line-height: 2;">' +
                '</label>' +
                '<label style="flex: 1.5; min-width: 150px; margin:0;">' +
                    '<span class="title" style="margin-bottom:4px;font-size:11px;line-height:1;">Description (opt)</span>' +
                    '<input type="text" class="zk-new-tag-desc" value="" autocomplete="off" style="width:100%; padding: 0 8px; line-height: 2;">' +
                '</label>' +
                '<button type="button" class="button button-secondary zk-add-tag-btn" style="margin-bottom: 0;">Add</button>' +
                '<span class="spinner zk-add-tag-spinner" style="float:none; margin: 0 0 5px 0;"></span>' +
            '</div>' +
            '<div class="zk-add-tag-feedback" style="color:#d63638; font-size:11px; margin-top:5px; display:none;"></div>' +
        '</div>';

        // Wait for WP to render the inline edit template, then append our form
        var injectInterval = setInterval(function() {
            var $rightCol = $('#inline-edit .inline-edit-col-right');
            var $tagList = $('#inline-edit ul.post_tag-checklist');
            if (!$tagList.length) $tagList = $('#inline-edit ul.post_tagchecklist'); // fallback
            
            if ($rightCol.length && $tagList.length) {
                if ($rightCol.find('.zk-custom-tag-adder').length === 0) {
                    var $tagsTitle = $tagList.prevAll('.inline-edit-categories-label').first();
                    var $tagsHidden = $tagList.prev('input[type="hidden"]');
                    
                    // Create a neat wrapper for the tags in the right column
                    var $tagsWrapper = $('<div class="inline-edit-col zk-moved-tags"></div>');
                    $tagsWrapper.append($tagsTitle).append($tagsHidden).append($tagList);
                    $tagsWrapper.css({ 'margin-top': '20px' });
                    
                    // Append the native tags and our custom form
                    $rightCol.append($tagsWrapper);
                    $rightCol.append(formHtml);
                    
                    clearInterval(injectInterval);
                }
            }
        }, 100);

        // Optional: generate nonce dynamically if not present
        var ajaxNonce = '<?php echo wp_create_nonce("zk_add_tag_nonce"); ?>';

        $('#the-list').on('click', '.zk-add-tag-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $row = $btn.closest('tr.inline-edit-row');
            var $nameInput = $row.find('.zk-new-tag-name');
            var $slugInput = $row.find('.zk-new-tag-slug');
            var $descInput = $row.find('.zk-new-tag-desc');
            var $spinner = $row.find('.zk-add-tag-spinner');
            var $feedback = $row.find('.zk-add-tag-feedback');

            var name = $.trim($nameInput.val());
            var slug = $.trim($slugInput.val());
            var desc = $.trim($descInput.val());
            var nonce = ajaxNonce;

            if ( ! name ) {
                $feedback.text('Name is required.').show();
                return;
            }

            $feedback.hide();
            $spinner.addClass('is-active');
            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'zk_add_quick_tag',
                nonce: nonce,
                tag_name: name,
                tag_slug: slug,
                tag_desc: desc
            }, function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);

                if ( response.success ) {
                    var termId = response.data.term_id;
                    var termName = response.data.name;
                    var $ul = $row.find('ul.post_tagchecklist');
                    
                    var newLi = $('<li id="post_tag-' + termId + '">' +
                        '<label class="selectit">' +
                        '<input value="' + termId + '" type="checkbox" name="tax_input[post_tag][]" id="in-post_tag-' + termId + '" checked="checked"> ' +
                        termName +
                        '</label></li>');
                    
                    $ul.prepend(newLi);
                    
                    $nameInput.val('');
                    $slugInput.val('');
                    $descInput.val('');
                    
                    $feedback.css('color', 'green').text('Tag added successfully!').show().fadeOut(3000, function() {
                        $feedback.css('color', 'red');
                    });
                } else {
                    $feedback.text(response.data || 'Error adding tag.').show();
                }
            }).fail(function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $feedback.text('Server error.').show();
            });
        });
    });
    </script>
    <?php
}

/* ============================================================
   SEO & ALGORITHMIC MAGNETISM (Dynamic Meta & JSON-LD)
   ============================================================ */
function zk_dynamic_seo_tags() {
    global $post;
    
    $site_name = get_bloginfo( 'name' );
    $title = wp_get_document_title();
    $desc = get_bloginfo( 'description' );
    $url = home_url( $_SERVER['REQUEST_URI'] );
    $image = '';
    $type = 'website';
    
    if ( is_singular() ) {
        if ( has_excerpt() ) {
            $desc = wp_strip_all_tags( get_the_excerpt() );
        } else {
            $desc = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
        }
        if ( has_post_thumbnail() ) {
            $image = get_the_post_thumbnail_url( get_the_ID(), 'full' );
        }
        $url = get_permalink();
        $type = 'article';
    } elseif ( is_archive() || is_search() ) {
        if ( is_tag() ) {
            $title = 'Topic: ' . single_tag_title( '', false ) . ' - ' . $site_name;
            $desc = wp_strip_all_tags( tag_description() );
        } elseif ( is_category() ) {
            $title = 'Category: ' . single_cat_title( '', false ) . ' - ' . $site_name;
            $desc = wp_strip_all_tags( category_description() );
        }
    }

    if ( empty( $desc ) ) {
        $desc = get_bloginfo( 'description' );
    }

    // Output Open Graph & Twitter Cards
    echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
    
    if ( $image ) {
        echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    } else {
        echo '<meta name="twitter:card" content="summary">' . "\n";
    }
    echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";

    // Output JSON-LD (Schema.org)
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => ( is_singular() && !is_front_page() ) ? 'Article' : 'WebSite',
        'name' => $title,
        'headline' => $title,
        'description' => $desc,
        'url' => $url,
        'author' => array(
            '@type' => 'Person',
            'name' => 'Zurab Kostava',
            'url' => home_url( '/' )
        )
    );
    
    if ( $image ) {
        $schema['image'] = $image;
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}
add_action( 'wp_head', 'zk_dynamic_seo_tags', 1 );

/* ============================================================
   MOBILE BOTTOM NAVIGATION (App-like UX)
   ============================================================ */
function zk_mobile_bottom_nav() {
    ?>
    <nav class="zk-bottom-nav" id="zk-bottom-nav">
        <a href="<?php echo home_url('/music/'); ?>" class="zk-bottom-nav-item" data-route="/music/">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
            <span>Music</span>
        </a>
        <a href="<?php echo home_url('/visual/'); ?>" class="zk-bottom-nav-item" data-route="/visual/">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            <span>Visual</span>
        </a>
        <a href="<?php echo home_url('/books/'); ?>" class="zk-bottom-nav-item" data-route="/books/">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>Books</span>
        </a>
        <a href="<?php echo home_url('/blog/'); ?>" class="zk-bottom-nav-item" data-route="/blog/">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Blogs</span>
        </a>
        <button id="zk-mobile-menu-trigger" class="zk-bottom-nav-item" aria-label="Open Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            <span>More</span>
        </button>
    </nav>
    <?php
}
add_action('wp_footer', 'zk_mobile_bottom_nav');
