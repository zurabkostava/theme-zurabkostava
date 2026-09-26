<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// --- 1. ADMIN META BOXES ---

function zk_add_translation_meta_boxes() {
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'zk_translation_meta_box',
            'Georgian Translation (ქართული თარგმანი)',
            'zk_render_translation_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'zk_add_translation_meta_boxes');

function zk_render_translation_meta_box($post) {
    wp_nonce_field('zk_save_translation_data', 'zk_translation_nonce');

    $title_ka = get_post_meta($post->ID, '_zk_title_ka', true);
    $content_ka = get_post_meta($post->ID, '_zk_content_ka', true);

    echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc; margin-bottom: 15px;">';
    echo '<p><label for="zk_title_ka"><strong>სათაური (Title)</strong></label></p>';
    echo '<input type="text" name="zk_title_ka" id="zk_title_ka" value="' . esc_attr($title_ka) . '" style="width: 100%; font-size: 16px; padding: 8px; margin-bottom: 15px;" />';
    
    echo '<p><strong>კონტენტი (Content Raw JSON/HTML)</strong></p>';
    echo '<div style="display: flex; gap: 10px; margin-bottom: 15px;">';
    echo '<button type="button" class="button button-primary" id="zk_copy_raw_btn">📋 Copy English Source</button>';
    echo '<button type="button" class="button button-secondary" id="zk_paste_raw_btn">📥 Paste Translated Source</button>';
    echo '</div>';

    // We will use a standard textarea for the raw source to make it easy to copy/paste JSON/Gutenberg blocks
    echo '<textarea name="zk_content_ka" id="zk_content_ka" style="width: 100%; height: 400px; font-family: monospace; padding: 10px; font-size: 13px;" placeholder="აქ ჩააკოპირე ნათარგმნი კონტენტი...">' . esc_textarea($content_ka) . '</textarea>';
    echo '</div>';

    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var copyBtn = document.getElementById("zk_copy_raw_btn");
        var pasteBtn = document.getElementById("zk_paste_raw_btn");
        var kaTextarea = document.getElementById("zk_content_ka");

        if(copyBtn) {
            copyBtn.addEventListener("click", function(e) {
                e.preventDefault();
                var englishContent = "";
                
                // Try Gutenberg
                if (window.wp && wp.data && wp.data.select("core/editor")) {
                    englishContent = wp.data.select("core/editor").getEditedPostContent();
                } else {
                    // Try Classic Editor
                    if (window.tinyMCE && tinyMCE.get("content")) {
                        englishContent = tinyMCE.get("content").getContent();
                    } else {
                        var contentTextarea = document.getElementById("content");
                        if (contentTextarea) {
                            englishContent = contentTextarea.value;
                        }
                    }
                }
                
                if (englishContent) {
                    navigator.clipboard.writeText(englishContent).then(function() {
                        alert("ინგლისური ტექსტი/JSON დაკოპირებულია კლიპბორდში! ახლა შეგიძლია გადათარგმნო.");
                    }).catch(function(err) {
                        alert("დაკოპირება ვერ მოხერხდა, გთხოვთ ხელით დააკოპიროთ (Error: " + err + ")");
                    });
                } else {
                    alert("ინგლისური კონტენტი ცარიელია.");
                }
            });
        }

        if (pasteBtn) {
            pasteBtn.addEventListener("click", async function(e) {
                e.preventDefault();
                try {
                    const text = await navigator.clipboard.readText();
                    if (text) {
                        kaTextarea.value = text;
                        alert("ტექსტი წარმატებით ჩაისვა!");
                    } else {
                        alert("კლიპბორდი ცარიელია.");
                    }
                } catch (err) {
                    alert("ტექსტის ჩასმა ვერ მოხერხდა. შესაძლოა ბრაუზერი ბლოკავს კლიპბორდთან წვდომას. გთხოვთ, პირდაპირ ველში გააკეთოთ Paste (Ctrl+V).");
                }
            });
        }
    });
    </script>
    <?php
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

    if (isset($_POST['zk_title_ka'])) {
        update_post_meta($post_id, '_zk_title_ka', sanitize_text_field($_POST['zk_title_ka']));
    }
    
    if (isset($_POST['zk_content_ka'])) {
        // Save unfiltered HTML to preserve Gutenberg comment tags and structures
        update_post_meta($post_id, '_zk_content_ka', $_POST['zk_content_ka']);
    }
}
add_action('save_post', 'zk_save_translation_meta_data');

// --- 2. EARLY URI REWRITING (FOOLPROOF METHOD) ---

function zk_early_uri_rewrite() {
    if (is_admin()) return;
    
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = parse_url($request_uri, PHP_URL_PATH);
    
    if (strpos($path, '/ka/') === 0 || $path === '/ka') {
        // Strip /ka/ from the URI
        $new_uri = preg_replace('#^/ka(?=/|$)#', '', $request_uri);
        if ($new_uri === '') $new_uri = '/';
        
        // Rewrite the server variable so WP processes it as the English URL
        $_SERVER['REQUEST_URI'] = $new_uri;
        
        // Set a constant so we know it was originally a Georgian request
        if (!defined('ZK_IS_GEORGIAN_REQUEST')) {
            define('ZK_IS_GEORGIAN_REQUEST', true);
        }
    }
    
    if (isset($_GET['zk_debug_uri'])) {
        die("REQUEST_URI is now: " . $_SERVER['REQUEST_URI'] . " | ZK_IS_GEORGIAN_REQUEST: " . (defined('ZK_IS_GEORGIAN_REQUEST') ? 'true' : 'false'));
    }
}
// Hook very early, even before setup_theme
add_action('plugins_loaded', 'zk_early_uri_rewrite', 1);

function zk_get_current_language() {
    if (is_admin()) return 'en';
    if (defined('ZK_IS_GEORGIAN_REQUEST') && ZK_IS_GEORGIAN_REQUEST) {
        return 'ka';
    }
    return 'en';
}

function zk_disable_canonical_for_ka($redirect_url, $requested_url) {
    if (zk_get_current_language() === 'ka') {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'zk_disable_canonical_for_ka', 10, 2);

// --- 3. FRONTEND CONTENT REPLACEMENT ---

function zk_translate_title($title, $post_id = null) {
    if (is_admin() || empty($post_id)) return $title;
    
    if (zk_get_current_language() === 'ka') {
        $translated_title = get_post_meta($post_id, '_zk_title_ka', true);
        if (!empty($translated_title)) {
            return $translated_title;
        }
    }
    return $title;
}
add_filter('the_title', 'zk_translate_title', 10, 2);

function zk_translate_content_wrapper($content) {
    if (is_admin()) return $content;
    
    static $is_filtering = false;
    if ($is_filtering) return $content;

    if (zk_get_current_language() === 'ka') {
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
add_filter('the_content', 'zk_translate_content_wrapper', 1);

// We purposefully omit the add_filter('post_link', ...) logic in this phase to prevent breaking the SPA routing.
