<?php
/**
 * Language Manager: Single Post Translation Architecture
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// --- 1. ADMIN META BOX ---

function zk_add_translation_meta_box() {
    add_meta_box(
        'zk_translation_meta_box',
        'Translations (თარგმანები)',
        'zk_render_translation_meta_box',
        array('post', 'page'),
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'zk_add_translation_meta_box');

function zk_render_translation_meta_box($post) {
    wp_nonce_field('zk_save_translation_data', 'zk_translation_nonce');

    $title_ka = get_post_meta($post->ID, '_zk_title_ka', true);
    $content_ka = get_post_meta($post->ID, '_zk_content_ka', true);

    echo '<h3>Georgian (ქართული)</h3>';
    
    // Title Field
    echo '<p><label for="zk_title_ka"><strong>სათაური (Title)</strong></label></p>';
    echo '<input type="text" name="zk_title_ka" id="zk_title_ka" value="' . esc_attr($title_ka) . '" style="width: 100%; font-size: 16px; padding: 5px;" />';
    
    // Content Editor
    echo '<p><label><strong>ტექსტი (Content)</strong></label></p>';
    wp_editor($content_ka, 'zk_content_ka', array(
        'textarea_name' => 'zk_content_ka',
        'media_buttons' => true,
        'textarea_rows' => 15,
        'teeny'         => false
    ));
}

function zk_save_translation_meta_data($post_id) {
    if (!isset($_POST['zk_translation_nonce']) || !wp_verify_nonce($_POST['zk_translation_nonce'], 'zk_save_translation_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save Georgian Fields
    if (isset($_POST['zk_title_ka'])) {
        update_post_meta($post_id, '_zk_title_ka', sanitize_text_field($_POST['zk_title_ka']));
    }
    
    if (isset($_POST['zk_content_ka'])) {
        // We use wp_kses_post to safely save HTML from the editor
        update_post_meta($post_id, '_zk_content_ka', wp_kses_post($_POST['zk_content_ka']));
    }
}
add_action('save_post', 'zk_save_translation_meta_data');


// --- 2. URL REWRITE RULES ---

function zk_language_rewrite_rules() {
    // Matches /ka/ at the root
    add_rewrite_rule('^ka/?$', 'index.php?lang_prefix=ka', 'top');
    // Matches /ka/post-slug/
    add_rewrite_rule('^ka/([^/]+)/?$', 'index.php?name=$matches[1]&lang_prefix=ka', 'top');
    // Matches /ka/category/...
    add_rewrite_rule('^ka/category/([^/]+)/?$', 'index.php?category_name=$matches[1]&lang_prefix=ka', 'top');
    // Matches /ka/page/2/
    add_rewrite_rule('^ka/page/([0-9]{1,})/?$', 'index.php?paged=$matches[1]&lang_prefix=ka', 'top');
}
add_action('init', 'zk_language_rewrite_rules');

function zk_language_query_vars($vars) {
    $vars[] = 'lang_prefix';
    return $vars;
}
add_filter('query_vars', 'zk_language_query_vars');

function zk_get_current_language() {
    if (is_admin()) return 'en'; // Backend logic
    $lang = get_query_var('lang_prefix');
    return ($lang === 'ka') ? 'ka' : 'en';
}

// --- 3. FRONT-END FILTERING ---

function zk_translate_title($title, $post_id = null) {
    if (is_admin() || empty($post_id)) return $title;
    
    $lang = zk_get_current_language();
    if ($lang === 'ka') {
        $translated_title = get_post_meta($post_id, '_zk_title_ka', true);
        if (!empty($translated_title)) {
            return $translated_title;
        }
    }
    return $title;
}
add_filter('the_title', 'zk_translate_title', 10, 2);

function zk_translate_content($content) {
    if (is_admin()) return $content;
    
    $lang = zk_get_current_language();
    if ($lang === 'ka') {
        $post_id = get_the_ID();
        $translated_content = get_post_meta($post_id, '_zk_content_ka', true);
        if (!empty($translated_content)) {
            // Apply normal WordPress content formatting (like paragraphs)
            return apply_filters('the_content', $translated_content, 999);
        }
    }
    return $content;
}
// We run this at a high priority but before shortcodes if needed. 
// Or just hook to `the_content` normally.
add_filter('the_content', 'zk_translate_content', 1);

// Prevent infinite loop from nested apply_filters
function zk_translate_content_wrapper($content) {
    if (is_admin()) return $content;
    
    static $is_filtering = false;
    if ($is_filtering) return $content;

    $lang = zk_get_current_language();
    if ($lang === 'ka') {
        $post_id = get_the_ID();
        $translated_content = get_post_meta($post_id, '_zk_content_ka', true);
        if (!empty($translated_content)) {
            $is_filtering = true;
            $filtered = apply_filters('the_content', $translated_content);
            $is_filtering = false;
            return $filtered;
        }
    }
    return $content;
}
remove_filter('the_content', 'zk_translate_content', 1);
add_filter('the_content', 'zk_translate_content_wrapper', 1);


// Rewrite Permalinks for internal links when viewing Georgian site
function zk_translate_permalink($url, $post) {
    if (is_admin()) return $url;
    
    $lang = zk_get_current_language();
    if ($lang === 'ka') {
        $home_url = home_url('/');
        if (strpos($url, $home_url) === 0) {
            // Check if already has /ka/
            $path = substr($url, strlen($home_url));
            if (strpos($path, 'ka/') !== 0) {
                return $home_url . 'ka/' . ltrim($path, '/');
            }
        }
    }
    return $url;
}
add_filter('post_link', 'zk_translate_permalink', 10, 2);
add_filter('page_link', 'zk_translate_permalink', 10, 2);
add_filter('post_type_link', 'zk_translate_permalink', 10, 2);


// Set Language Attributes on HTML
function zk_language_attributes($output) {
    $lang = zk_get_current_language();
    if ($lang === 'ka') {
        return 'lang="ka"';
    }
    return $output; // fallback to WP default
}
add_filter('language_attributes', 'zk_language_attributes');


// --- 4. UI SWITCHER URL HELPER ---

function zk_get_translation_url($lang) {
    global $wp;
    
    // Get current clean URL without /ka/
    $current_url = home_url(add_query_arg(array(), $wp->request));
    $home_url = home_url('/');
    
    $path = '/';
    if (strpos($current_url, $home_url) === 0) {
        $path = '/' . ltrim(substr($current_url, strlen($home_url)), '/');
    }
    
    // Strip /ka/ from path if it exists
    if (strpos($path, '/ka/') === 0) {
        $path = '/' . substr($path, 4);
    }
    
    if ($lang === 'ka') {
        return home_url('/ka' . $path);
    } else {
        return home_url($path);
    }
}

// Generate Hreflang Tags in Head
function zk_hreflang_tags() {
    $en_url = zk_get_translation_url('en');
    $ka_url = zk_get_translation_url('ka');

    echo '<link rel="alternate" hreflang="en" href="' . esc_url($en_url) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($en_url) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="ka" href="' . esc_url($ka_url) . '" />' . "\n";
}
add_action('wp_head', 'zk_hreflang_tags', 1);
