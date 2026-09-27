<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// --- 1. ADMIN META BOXES ---

function zk_add_translation_meta_boxes() {
    $post_types = get_post_types(array('public' => true), 'names');
    $post_types['zk_music_release'] = 'zk_music_release';
    $post_types['zk_book'] = 'zk_book';
    $post_types['zk_tool'] = 'zk_tool';
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
    $excerpt_ka = get_post_meta($post->ID, '_zk_excerpt_ka', true);
    $content_ka = get_post_meta($post->ID, '_zk_content_ka', true);
    $orig_excerpt = $post->post_excerpt;

    echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ccc; margin-bottom: 15px;">';
    echo '<p><label for="zk_title_ka"><strong>სათაური (Title)</strong></label></p>';
    echo '<input type="text" name="zk_title_ka" id="zk_title_ka" value="' . esc_attr($title_ka) . '" style="width: 100%; font-size: 16px; padding: 8px; margin-bottom: 15px;" />';

    echo '<p><label for="zk_excerpt_ka"><strong>მოკლე აღწერა / Excerpt (ქართულად)</strong></label></p>';
    if (!empty($orig_excerpt)) {
        echo '<p style="margin: 0 0 5px; color: #666; font-size: 12px; background: #fff; padding: 6px 10px; border: 1px dashed #ccc; border-radius: 4px;"><strong>ორიგინალი (English Excerpt):</strong> ' . esc_html($orig_excerpt) . '</p>';
    }
    echo '<textarea name="zk_excerpt_ka" id="zk_excerpt_ka" rows="3" style="width: 100%; font-size: 14px; padding: 8px; margin-bottom: 15px;" placeholder="აქ ჩაწერეთ ქართული მოკლე აღწერა...">' . esc_textarea($excerpt_ka) . '</textarea>';
    
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

    if (isset($_POST['zk_excerpt_ka'])) {
        update_post_meta($post_id, '_zk_excerpt_ka', sanitize_textarea_field($_POST['zk_excerpt_ka']));
    }
    
    if (isset($_POST['zk_content_ka'])) {
        // Save unfiltered HTML but clean any malformed markdown links inside src/href attributes
        $clean_content = zk_clean_markdown_attributes($_POST['zk_content_ka']);
        update_post_meta($post_id, '_zk_content_ka', $clean_content);
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
}
// Hook early in init
add_action('init', 'zk_early_uri_rewrite', 1);

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

function zk_translate_excerpt($excerpt, $post = null) {
    if (is_admin()) return $excerpt;
    
    if (zk_get_current_language() === 'ka') {
        $post_id = null;
        if (is_object($post) && isset($post->ID)) {
            $post_id = $post->ID;
        } elseif (is_numeric($post) && (int)$post > 0) {
            $post_id = (int)$post;
        } else {
            $post_id = get_the_ID();
        }
        
        if ($post_id) {
            $excerpt_ka = get_post_meta($post_id, '_zk_excerpt_ka', true);
            if (!empty($excerpt_ka)) {
                return $excerpt_ka;
            }
        }
    }
    return $excerpt;
}
add_filter('get_the_excerpt', 'zk_translate_excerpt', 999999, 2);
add_filter('the_excerpt', 'zk_translate_excerpt', 999999, 1);
add_filter('wp_trim_excerpt', 'zk_translate_excerpt', 999999, 2);

function zk_translate_has_excerpt($has_excerpt, $post = null) {
    if (is_admin()) return $has_excerpt;
    
    if (zk_get_current_language() === 'ka') {
        $post_id = null;
        if (is_object($post) && isset($post->ID)) {
            $post_id = $post->ID;
        } elseif (is_numeric($post) && (int)$post > 0) {
            $post_id = (int)$post;
        } else {
            $post_id = get_the_ID();
        }
        
        if ($post_id) {
            $excerpt_ka = get_post_meta($post_id, '_zk_excerpt_ka', true);
            if (!empty($excerpt_ka)) {
                return true;
            }
        }
    }
    return $has_excerpt;
}
add_filter('has_excerpt', 'zk_translate_has_excerpt', 999999, 2);

function zk_clean_markdown_attributes($content) {
    if (empty($content) || !is_string($content)) return $content;

    // 1. Full markdown link format: [url](url) or [url]
    $content = preg_replace_callback('/\[(https?:\/\/[^\]]+)\](?:\((https?:\/\/[^\)]+)\))?/i', function($m) {
        return !empty($m[2]) ? $m[2] : $m[1];
    }, $content);

    // 2. Trailing markdown link target inside attribute value: ](https://...)
    $content = preg_replace('/\]\([^"\']+/i', '', $content);

    // 3. Leading bracket inside attribute value: src="[... / href="[...
    $content = preg_replace('/(\bsrc|\bhref)=(["\'])\[/i', '$1=$2', $content);

    // 4. Trailing parenthesis before closing quote: src="...)"
    $content = preg_replace('/\)\s*(["\'])/i', '$1', $content);

    return $content;
}
add_filter('the_content', 'zk_clean_markdown_attributes', 999999);

add_filter('wp_calculate_image_srcset', function($sources) {
    if (is_array($sources)) {
        foreach ($sources as &$src) {
            if (isset($src['url'])) {
                $src['url'] = zk_clean_markdown_attributes($src['url']);
            }
        }
    }
    return $sources;
}, 999999);

// Automatically clean and fix malformed markdown link syntax in DB meta on page load
function zk_auto_clean_db_meta() {
    if (is_singular()) {
        $post_id = get_the_ID();
        if ($post_id) {
            $raw_ka = get_post_meta($post_id, '_zk_content_ka', true);
            if (!empty($raw_ka)) {
                $cleaned = zk_clean_markdown_attributes($raw_ka);
                if ($cleaned !== $raw_ka) {
                    update_post_meta($post_id, '_zk_content_ka', $cleaned);
                }
            }
        }
    }
}
add_action('wp', 'zk_auto_clean_db_meta');

function zk_translate_content_wrapper($content) {
    if (is_admin()) return $content;
    
    if (zk_get_current_language() === 'ka') {
        $post_id = get_the_ID();
        $translated_content = get_post_meta($post_id, '_zk_content_ka', true);
        if (!empty($translated_content)) {
            $clean_ka = zk_clean_markdown_attributes($translated_content);
            remove_filter('the_content', 'zk_translate_content_wrapper', 1);
            $filtered = apply_filters('the_content', $clean_ka);
            add_filter('the_content', 'zk_translate_content_wrapper', 1);
            return zk_clean_markdown_attributes($filtered);
        }
    }
    return $content;
}
add_filter('the_content', 'zk_translate_content_wrapper', 1);


function zk_filter_permalink_ka($permalink, $post = null) {
    if (is_admin()) return $permalink;
    if (zk_get_current_language() === 'ka') {
        $parsed = parse_url($permalink);
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        
        if (!empty($path) && strpos($path, '/ka/') !== 0 && $path !== '/ka') {
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
            $host   = isset($parsed['host']) ? $parsed['host'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            
            $new_path = '/ka' . ($path[0] === '/' ? '' : '/') . $path;
            return $scheme . '://' . $host . $port . $new_path . $query;
        }
    }
    return $permalink;
}
add_filter('post_link', 'zk_filter_permalink_ka', 10, 2);
add_filter('page_link', 'zk_filter_permalink_ka', 10, 2);
add_filter('post_type_link', 'zk_filter_permalink_ka', 10, 2);
add_filter('term_link', 'zk_filter_permalink_ka', 10, 2);
add_filter('category_link', 'zk_filter_permalink_ka', 10, 2);
add_filter('tag_link', 'zk_filter_permalink_ka', 10, 2);

function zk_change_html_lang_ka($output) {
    if (zk_get_current_language() === 'ka') {
        return 'lang="ka-GE"';
    }
    return $output;
}
add_filter('language_attributes', 'zk_change_html_lang_ka');

function zk_filter_home_url_ka($url, $path = '', $orig_scheme = null, $blog_id = null) {
    if (is_admin()) return $url;
    if (zk_get_current_language() === 'ka') {
        $parsed = parse_url($url);
        $p = isset($parsed['path']) ? $parsed['path'] : '';
        if (strpos($p, '/ka') !== 0) {
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
            $host   = isset($parsed['host']) ? $parsed['host'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            
            $new_path = '/ka' . ($p === '/' || $p === '' ? '/' : ($p[0] === '/' ? $p : '/' . $p));
            return $scheme . '://' . $host . $port . $new_path . $query;
        }
    }
    return $url;
}
add_filter('home_url', 'zk_filter_home_url_ka', 10, 4);

// Send nocache headers on ka requests to prevent browsers/CDNs from caching old 301 redirects
function zk_ka_nocache_headers() {
    if (zk_get_current_language() === 'ka') {
        nocache_headers();
    }
}
add_action('send_headers', 'zk_ka_nocache_headers');

// --- 4. TAXONOMY TRANSLATION SUPPORT (CATEGORIES & TAGS) ---

function zk_get_default_term_translation($slug) {
    static $dict = array(
        // Categories
        'blog' => 'ბლოგი',
        'news' => 'სიახლეები',
        'reviews' => 'მიმოხილვები',
        'raw' => 'გაუფილტრავი',
        'aubades' => 'დილისპირულები',
        'nocturnes' => 'ძილისპირულები',

        // Tags
        'art-tech' => 'არტ ტექნოლოგია',
        'artificial-intelligence' => 'ხელოვნური ინტელექტი',
        'artists-struggle' => 'ხელოვანის ბრძოლა',
        'audio-tech' => 'აუდიო ტექნოლოგია',
        'authenticity' => 'ავთენტურობა',
        'behind-the-scenes' => 'კულისებს მიღმა',
        'cinematic-narrative' => 'კინემატოგრაფიული თხრობა',
        'creative-identity' => 'შემოქმედებითი იდენტობა',
        'creative-process' => 'შემოქმედებითი პროცესი',
        'digital-art' => 'ციფრული ხელოვნება',
        'digital-manifesto' => 'ციფრული მანიფესტი',
        'entrepreneurship' => 'ანტრეპრენერობა',
        'everyday-observations' => 'ყოველდღიური დაკვირვებები',
        'existentialism' => 'ეგზისტენციალიზმი',
        'generative-art' => 'გენერაციული ხელოვნება',
        'georgian-heritage' => 'ქართული მემკვიდრეობა',
        'human-connection' => 'ადამიანური კავშირი',
        'humor-and-satire' => 'იუმორი და სატირა',
        'kostava-creative' => 'Kostava Creative',
        'making-music' => 'მუსიკის შექმნა',
        'melancholy-loneliness' => 'მელანქოლია და მარტოობა',
        'mental-health' => 'მენტალური ჯანმრთელობა',
        'multimedia' => 'მულტიმედია',
        'new-beginings' => 'ახალი დასაწყისი',
        'nostalgia' => 'ნოსტალგია',
        'parenthood' => 'მშობლობა',
        'personal-essay' => 'პირადი ესე',
        'photography-philosophy' => 'ფოტოგრაფიის ფილოსოფია',
        'reading' => 'კითხვა',
        'self-discovery' => 'თვითშემეცნება',
        'simulation-theory' => 'სიმულაციის თეორია',
        'storytelling' => 'სთორითელინგი',
        'urban-vignettes' => 'ურბანული ჩანახატები',
        'vusual-language' => 'ვიზუალური ენა',
    );
    return isset($dict[$slug]) ? $dict[$slug] : '';
}

function zk_get_translated_term_name($term) {
    if (empty($term)) return '';
    if (is_numeric($term)) {
        $term = get_term((int)$term);
    }
    if (!is_object($term) || is_wp_error($term)) return '';

    if (function_exists('zk_get_current_language') && zk_get_current_language() === 'ka') {
        $meta_name = get_term_meta($term->term_id, '_zk_name_ka', true);
        if (!empty($meta_name)) {
            return $meta_name;
        }
        $default_name = zk_get_default_term_translation($term->slug);
        if (!empty($default_name)) {
            return $default_name;
        }
    }
    return $term->name;
}

function zk_taxonomy_add_custom_fields($taxonomy) {
    ?>
    <div class="form-field term-group">
        <label for="zk_name_ka"><strong>ქართული სახელი (Georgian Name)</strong></label>
        <input type="text" id="zk_name_ka" name="zk_name_ka" value="" placeholder="მაგ. ხელოვნური ინტელექტი">
        <p class="description">მიუთითეთ თეგის/კატეგორიის ქართული თარგმანი ქართულენოვანი გვერდებისთვის.</p>
    </div>
    <?php
}

function zk_taxonomy_edit_custom_fields($term, $taxonomy) {
    $name_ka = get_term_meta($term->term_id, '_zk_name_ka', true);
    if (empty($name_ka)) {
        $name_ka = zk_get_default_term_translation($term->slug);
    }
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="zk_name_ka">ქართული სახელი (Georgian Name)</label></th>
        <td>
            <input type="text" id="zk_name_ka" name="zk_name_ka" value="<?php echo esc_attr($name_ka); ?>" style="width: 100%; max-width: 400px; font-size: 15px; padding: 6px 10px;">
            <p class="description">მიუთითეთ თეგის/კატეგორიის ქართული თარგმანი ქართულენოვანი გვერდებისთვის.</p>
        </td>
    </tr>
    <?php
}

function zk_save_taxonomy_custom_fields($term_id) {
    if (isset($_POST['zk_name_ka'])) {
        update_term_meta($term_id, '_zk_name_ka', sanitize_text_field($_POST['zk_name_ka']));
    }
}

function zk_taxonomy_columns($columns) {
    $columns['zk_name_ka'] = 'ქართული სახელი';
    return $columns;
}

function zk_taxonomy_custom_column($content, $column_name, $term_id) {
    if ($column_name === 'zk_name_ka') {
        $term = get_term($term_id);
        $val = get_term_meta($term_id, '_zk_name_ka', true);
        if (empty($val) && $term && !is_wp_error($term)) {
            $val = zk_get_default_term_translation($term->slug);
            if (!empty($val)) {
                return '<span style="color: #666; font-style: italic;">' . esc_html($val) . ' (default)</span>';
            }
        }
        return $val ? '<strong>' . esc_html($val) . '</strong>' : '<span style="color:#bbb;">—</span>';
    }
    return $content;
}

foreach (array('category', 'post_tag') as $tax) {
    add_action("{$tax}_add_form_fields", 'zk_taxonomy_add_custom_fields');
    add_action("{$tax}_edit_form_fields", 'zk_taxonomy_edit_custom_fields', 10, 2);
    add_action("created_{$tax}", 'zk_save_taxonomy_custom_fields');
    add_action("edited_{$tax}", 'zk_save_taxonomy_custom_fields');
    add_filter("manage_edit-{$tax}_columns", 'zk_taxonomy_columns');
    add_filter("manage_{$tax}_custom_column", 'zk_taxonomy_custom_column', 10, 3);
}

// Frontend term translation filters
function zk_filter_get_term($term, $taxonomy = '') {
    if (is_admin()) return $term;
    if (function_exists('zk_get_current_language') && zk_get_current_language() === 'ka') {
        if (is_object($term) && isset($term->term_id)) {
            $trans = get_term_meta($term->term_id, '_zk_name_ka', true);
            if (empty($trans) && isset($term->slug)) {
                $trans = zk_get_default_term_translation($term->slug);
            }
            if (!empty($trans)) {
                $term->name = $trans;
            }
        }
    }
    return $term;
}
add_filter('get_term', 'zk_filter_get_term', 10, 2);

function zk_filter_single_term_title($title) {
    if (is_admin()) return $title;
    if (function_exists('zk_get_current_language') && zk_get_current_language() === 'ka') {
        $obj = get_queried_object();
        if ($obj && isset($obj->term_id)) {
            $trans = get_term_meta($obj->term_id, '_zk_name_ka', true);
            if (empty($trans) && isset($obj->slug)) {
                $trans = zk_get_default_term_translation($obj->slug);
            }
            if (!empty($trans)) {
                return $trans;
            }
        }
    }
    return $title;
}
add_filter('single_term_title', 'zk_filter_single_term_title', 10, 1);

// --- 5. LANGUAGE SWITCHER (TOP RIGHT HEADER) ---

function zk_get_language_switcher_urls() {
    $current_lang = function_exists('zk_get_current_language') ? zk_get_current_language() : 'en';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $parsed = parse_url($request_uri);
    $path = isset($parsed['path']) ? $parsed['path'] : '/';
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

    // Strip leading /ka or /ka/ from path to obtain the pure English path
    $en_path = preg_replace('#^/ka(?=/|$)#', '', $path);
    if ($en_path === '') {
        $en_path = '/';
    }

    // Ensure leading slash
    if ($en_path[0] !== '/') {
        $en_path = '/' . $en_path;
    }

    // Georgian path is prefixed with /ka
    $ka_path = ($en_path === '/') ? '/ka/' : '/ka' . $en_path;

    $home_url = untrailingslashit(get_option('home'));
    if (empty($home_url)) {
        $home_url = 'https://zurabkostava.com';
    }

    return array(
        'current'  => $current_lang,
        'en_url'   => $home_url . $en_path . $query,
        'en_route' => $en_path . $query,
        'ka_url'   => $home_url . $ka_path . $query,
        'ka_route' => $ka_path . $query,
    );
}

function zk_render_language_switcher() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;

    $data = zk_get_language_switcher_urls();
    $current = $data['current'];
    ?>
    <div class="zk-lang-switcher" role="navigation" aria-label="Language selector">
        <a href="<?php echo esc_url($data['en_url']); ?>" 
           data-route="<?php echo esc_attr($data['en_route']); ?>"
           class="zk-lang-btn <?php echo ($current === 'en') ? 'is-active' : ''; ?>" 
           aria-label="English language"
           <?php echo ($current === 'en') ? 'aria-current="true"' : ''; ?>>
            <span>EN</span>
        </a>
        <span class="zk-lang-divider" aria-hidden="true"></span>
        <a href="<?php echo esc_url($data['ka_url']); ?>" 
           data-route="<?php echo esc_attr($data['ka_route']); ?>"
           class="zk-lang-btn <?php echo ($current === 'ka') ? 'is-active' : ''; ?>" 
           aria-label="ქართული ენა"
           <?php echo ($current === 'ka') ? 'aria-current="true"' : ''; ?>>
            <span>KA</span>
        </a>
    </div>
    <?php
}

/**
 * Convert Georgian text to Mtavruli (uppercase) and Latin to uppercase.
 * Supports Unicode Mkhedruli (U+10D0..U+10FA) -> Mtavruli (U+1C90..U+1CBA) mapping.
 *
 * @param string $text
 * @return string
 */
if ( ! function_exists( 'zk_uppercase_ka' ) ) {
    function zk_uppercase_ka( $text ) {
        if ( empty( $text ) || ! is_string( $text ) ) {
            return $text;
        }

        static $map = null;
        if ( null === $map ) {
            $map = array(
                'ა' => 'Ა', 'ბ' => 'Ბ', 'გ' => 'Გ', 'დ' => 'Დ', 'ე' => 'Ე',
                'ვ' => 'Ვ', 'ზ' => 'Ზ', 'თ' => 'Თ', 'ი' => 'Ი', 'კ' => 'Კ',
                'ლ' => 'Ლ', 'მ' => 'Მ', 'ნ' => 'Ნ', 'ო' => 'Ო', 'პ' => 'Პ',
                'ჟ' => 'Ჟ', 'რ' => 'Რ', 'ს' => 'Ს', 'ტ' => 'Ტ', 'უ' => 'Უ',
                'ფ' => 'Ფ', 'ქ' => 'Ქ', 'ღ' => 'Ღ', 'ყ' => 'Ყ', 'შ' => 'Შ',
                'ჩ' => 'Ჩ', 'ც' => 'Ც', 'ძ' => 'Ძ', 'წ' => 'Წ', 'ჭ' => 'Ჭ',
                'ხ' => 'Ხ', 'ჯ' => 'Ჯ', 'ჰ' => 'Ჰ'
            );
        }

        $converted = strtr( $text, $map );
        return strtoupper( $converted );
    }
}

/**
 * Format a timestamp into Georgian date.
 *
 * @param int|string $timestamp Unix timestamp or date string
 * @param string $format PHP date format string
 * @return string
 */
if ( ! function_exists( 'zk_format_date_ka' ) ) {
    function zk_format_date_ka( $timestamp, $format = 'j F, Y' ) {
        if ( empty( $timestamp ) ) return '';
        if ( ! is_numeric( $timestamp ) ) {
            $timestamp = strtotime( $timestamp );
        }
        if ( ! $timestamp ) return '';

        // If format is ISO / machine readable, keep original
        if ( in_array( $format, array( 'c', 'r', 'U', 'Y-m-d', 'Y-m-d H:i:s' ), true ) ) {
            return date( $format, $timestamp );
        }

        static $months_full = array(
            1 => 'იანვარი',
            2 => 'თებერვალი',
            3 => 'მარტი',
            4 => 'აპრილი',
            5 => 'მაისი',
            6 => 'ივნისი',
            7 => 'ივლისი',
            8 => 'აგვისტო',
            9 => 'სექტემბერი',
            10 => 'ოქტომბერი',
            11 => 'ნოემბერი',
            12 => 'დეკემბერი'
        );

        static $days_full = array(
            0 => 'კვირა',
            1 => 'ორშაბათი',
            2 => 'სამშაბათი',
            3 => 'ოთხშაბათი',
            4 => 'ხუთშაბათი',
            5 => 'პარასკევი',
            6 => 'შაბათი'
        );

        static $days_short = array(
            0 => 'კვ',
            1 => 'ორშ',
            2 => 'სამ',
            3 => 'ოთხ',
            4 => 'ხუთ',
            5 => 'პარ',
            6 => 'შაბ'
        );

        $m_num = (int) date( 'n', $timestamp );
        $w_num = (int) date( 'w', $timestamp );

        // If format is American m/d/y -> convert to Georgian d/m/y
        if ( $format === 'm/d/y' || $format === 'm/d/Y' ) {
            return date( 'd/m/y', $timestamp );
        }

        if ( in_array( $format, array( 'M j, Y', 'M j,Y', 'F j, Y', 'F j,Y', 'j F Y', 'j M Y' ), true ) ) {
            return date( 'j', $timestamp ) . ' ' . $months_full[ $m_num ] . ', ' . date( 'Y', $timestamp );
        }

        if ( $format === 'M j' || $format === 'F j' || $format === 'j M' || $format === 'j F' ) {
            return date( 'j', $timestamp ) . ' ' . $months_full[ $m_num ];
        }

        if ( $format === 'M Y' || $format === 'F Y' ) {
            return $months_full[ $m_num ] . ' ' . date( 'Y', $timestamp );
        }

        // General token replacement
        $chars = str_split( $format );
        $result = '';
        $len = count( $chars );
        for ( $i = 0; $i < $len; $i++ ) {
            $c = $chars[$i];
            if ( $c === '\\' && $i + 1 < $len ) {
                $result .= $chars[++$i];
                continue;
            }
            switch ( $c ) {
                case 'F':
                case 'M':
                    $result .= $months_full[ $m_num ];
                    break;
                case 'l':
                    $result .= $days_full[ $w_num ];
                    break;
                case 'D':
                    $result .= $days_short[ $w_num ];
                    break;
                default:
                    $result .= date( $c, $timestamp );
                    break;
            }
        }

        return $result;
    }
}

/**
 * Translate English month names inside an arbitrary date string to Georgian.
 */
if ( ! function_exists( 'zk_translate_date_string_ka' ) ) {
    function zk_translate_date_string_ka( $date_str ) {
        if ( empty( $date_str ) || ! is_string( $date_str ) ) return $date_str;

        static $en_to_ka = array(
            'january'   => 'იანვარი', 'jan'  => 'იანვარი',
            'february'  => 'თებერვალი', 'feb' => 'თებერვალი',
            'march'     => 'მარტი', 'mar'    => 'მარტი',
            'april'     => 'აპრილი', 'apr'   => 'აპრილი',
            'may'       => 'მაისი',
            'june'      => 'ივნისი', 'jun'   => 'ივნისი',
            'july'      => 'ივლისი', 'jul'   => 'ივლისი',
            'august'    => 'აგვისტო', 'aug'  => 'აგვისტო',
            'september' => 'სექტემბერი', 'sep' => 'სექტემბერი', 'sept' => 'სექტემბერი',
            'october'   => 'ოქტომბერი', 'oct' => 'ოქტომბერი',
            'november'  => 'ნოემბერი', 'nov' => 'ნოემბერი',
            'december'  => 'დეკემბერი', 'dec' => 'დეკემბერი',
        );

        // 1. Pattern: "Jun 15, 2026" or "August 13, 2024" -> "15 ივნისი, 2026"
        $date_str = preg_replace_callback(
            '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i',
            function( $m ) use ( $en_to_ka ) {
                $mon = strtolower( $m[1] );
                $ka_mon = isset( $en_to_ka[ $mon ] ) ? $en_to_ka[ $mon ] : $m[1];
                return $m[2] . ' ' . $ka_mon . ', ' . $m[3];
            },
            $date_str
        );

        // 2. Pattern: "15 June 2026" -> "15 ივნისი, 2026"
        $date_str = preg_replace_callback(
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?),?\s+(\d{4})\b/i',
            function( $m ) use ( $en_to_ka ) {
                $mon = strtolower( $m[2] );
                $ka_mon = isset( $en_to_ka[ $mon ] ) ? $en_to_ka[ $mon ] : $m[2];
                return $m[1] . ' ' . $ka_mon . ', ' . $m[3];
            },
            $date_str
        );

        // 3. Pattern: "October 2024" -> "ოქტომბერი 2024"
        $date_str = preg_replace_callback(
            '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{4})\b/i',
            function( $m ) use ( $en_to_ka ) {
                $mon = strtolower( $m[1] );
                $ka_mon = isset( $en_to_ka[ $mon ] ) ? $en_to_ka[ $mon ] : $m[1];
                return $ka_mon . ' ' . $m[2];
            },
            $date_str
        );

        // 4. Pattern: mm/dd/yy -> dd/mm/yy
        $date_str = preg_replace_callback(
            '/\b(\d{2})\/(\d{2})\/(\d{2,4})\b/',
            function( $m ) {
                $m_num = (int) $m[1];
                $d_num = (int) $m[2];
                if ( $m_num >= 1 && $m_num <= 12 && $d_num >= 1 && $d_num <= 31 ) {
                    return sprintf( '%02d/%02d/%s', $d_num, $m_num, $m[3] );
                }
                return $m[0];
            },
            $date_str
        );

        return $date_str;
    }
}

// Hook WordPress date functions for Georgian language
add_filter( 'get_the_date', function( $the_date, $format = '', $post = null ) {
    if ( function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka' ) {
        if ( in_array( $format, array( 'c', 'r', 'U' ), true ) ) {
            return $the_date;
        }
        $timestamp = 0;
        if ( is_object( $post ) && isset( $post->ID ) ) {
            $timestamp = get_post_time( 'U', false, $post );
        } elseif ( is_numeric( $post ) ) {
            $timestamp = get_post_time( 'U', false, $post );
        } else {
            $timestamp = get_the_time( 'U' );
        }
        if ( ! $timestamp && ! empty( $the_date ) ) {
            $timestamp = strtotime( $the_date );
        }
        if ( $timestamp ) {
            if ( empty( $format ) ) {
                $format = get_option( 'date_format', 'F j, Y' );
            }
            return zk_format_date_ka( $timestamp, $format );
        }
    }
    return $the_date;
}, 10, 3 );

add_filter( 'get_the_modified_date', function( $the_date, $format = '', $post = null ) {
    if ( function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka' ) {
        if ( in_array( $format, array( 'c', 'r', 'U' ), true ) ) {
            return $the_date;
        }
        $timestamp = get_post_modified_time( 'U', false, $post );
        if ( ! $timestamp && ! empty( $the_date ) ) {
            $timestamp = strtotime( $the_date );
        }
        if ( $timestamp ) {
            if ( empty( $format ) ) {
                $format = get_option( 'date_format', 'F j, Y' );
            }
            return zk_format_date_ka( $timestamp, $format );
        }
    }
    return $the_date;
}, 10, 3 );

add_filter( 'wp_date', function( $date, $format, $timestamp, $timezone ) {
    if ( function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka' ) {
        if ( in_array( $format, array( 'c', 'r', 'U' ), true ) ) {
            return $date;
        }
        return zk_format_date_ka( $timestamp, $format );
    }
    return $date;
}, 10, 4 );

add_filter( 'date_i18n', function( $date, $req_format, $i, $gmt ) {
    if ( function_exists( 'zk_get_current_language' ) && zk_get_current_language() === 'ka' ) {
        if ( in_array( $req_format, array( 'c', 'r', 'U' ), true ) ) {
            return $date;
        }
        return zk_format_date_ka( $i, $req_format );
    }
    return $date;
}, 10, 4 );

