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
            'Translations',
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
    $orig_excerpt = $post->post_excerpt;

    foreach ( zk_get_translatable_languages( false ) as $code => $language ) {
        $title   = get_post_meta( $post->ID, zk_language_meta_key( 'title', $code ), true );
        $excerpt = get_post_meta( $post->ID, zk_language_meta_key( 'excerpt', $code ), true );
        $content = get_post_meta( $post->ID, zk_language_meta_key( 'content', $code ), true );
        $name    = $language['native_name'] . ' (' . strtoupper( $code ) . ')';

        echo '<details style="background:#f9f9f9;padding:15px;border:1px solid #ccc;margin-bottom:15px;" ' . ( 'ka' === $code ? 'open' : '' ) . '>';
        echo '<summary style="cursor:pointer;font-size:16px"><strong>' . esc_html( $name ) . '</strong>' . ( empty( $language['enabled'] ) ? ' — Disabled' : '' ) . '</summary>';
        echo '<p><label for="zk_title_' . esc_attr( $code ) . '"><strong>Translated title</strong></label></p>';
        echo '<input type="text" name="zk_title_' . esc_attr( $code ) . '" id="zk_title_' . esc_attr( $code ) . '" value="' . esc_attr( $title ) . '" style="width:100%;font-size:16px;padding:8px;margin-bottom:15px">';
        echo '<p><label for="zk_excerpt_' . esc_attr( $code ) . '"><strong>Translated excerpt</strong></label></p>';
        if ( ! empty( $orig_excerpt ) ) {
            echo '<p style="margin:0 0 5px;color:#666;font-size:12px;background:#fff;padding:6px 10px;border:1px dashed #ccc"><strong>English source:</strong> ' . esc_html( $orig_excerpt ) . '</p>';
        }
        echo '<textarea name="zk_excerpt_' . esc_attr( $code ) . '" id="zk_excerpt_' . esc_attr( $code ) . '" rows="3" style="width:100%;font-size:14px;padding:8px;margin-bottom:15px">' . esc_textarea( $excerpt ) . '</textarea>';
        echo '<p><strong>Translated content (raw blocks/HTML)</strong></p>';
        echo '<div style="display:flex;gap:10px;margin-bottom:15px"><button type="button" class="button button-primary zk-copy-source" data-target="zk_content_' . esc_attr( $code ) . '">Copy English source</button><button type="button" class="button zk-paste-translation" data-target="zk_content_' . esc_attr( $code ) . '">Paste translation</button></div>';
        echo '<textarea name="zk_content_' . esc_attr( $code ) . '" id="zk_content_' . esc_attr( $code ) . '" style="width:100%;height:320px;font-family:monospace;padding:10px;font-size:13px">' . esc_textarea( $content ) . '</textarea>';
        echo '</details>';
    }

    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".zk-copy-source").forEach(function(copyBtn) {
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
                        alert("English source copied.");
                    }).catch(function(err) {
                        alert("Copy failed. Please copy the source manually.");
                    });
                } else {
                    alert("English content is empty.");
                }
            });
        });

        document.querySelectorAll(".zk-paste-translation").forEach(function(pasteBtn) {
            pasteBtn.addEventListener("click", async function(e) {
                e.preventDefault();
                try {
                    const text = await navigator.clipboard.readText();
                    if (text) {
                        var target = document.getElementById(pasteBtn.dataset.target);
                        if (target) target.value = text;
                        alert("Translation pasted.");
                    } else {
                        alert("Clipboard is empty.");
                    }
                } catch (err) {
                    alert("Paste failed. Please use Ctrl+V in the field.");
                }
            });
        });
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

    foreach ( zk_get_translatable_languages( false ) as $code => $language ) {
        $title_key   = 'zk_title_' . $code;
        $excerpt_key = 'zk_excerpt_' . $code;
        $content_key = 'zk_content_' . $code;
        if ( isset( $_POST[ $title_key ] ) ) {
            update_post_meta( $post_id, zk_language_meta_key( 'title', $code ), sanitize_text_field( wp_unslash( $_POST[ $title_key ] ) ) );
        }
        if ( isset( $_POST[ $excerpt_key ] ) ) {
            update_post_meta( $post_id, zk_language_meta_key( 'excerpt', $code ), sanitize_textarea_field( wp_unslash( $_POST[ $excerpt_key ] ) ) );
        }
        if ( isset( $_POST[ $content_key ] ) ) {
            $clean_content = zk_clean_markdown_attributes( wp_unslash( $_POST[ $content_key ] ) );
            update_post_meta( $post_id, zk_language_meta_key( 'content', $code ), $clean_content );
        }
    }
}
add_action('save_post', 'zk_save_translation_meta_data');

// --- 2. EARLY URI REWRITING (FOOLPROOF METHOD) ---

function zk_early_uri_rewrite() {
    if (is_admin()) return;
    
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    // Keep the browser URL before WordPress receives the unprefixed route.
    if ( ! defined( 'ZK_ORIGINAL_REQUEST_URI' ) ) {
        define( 'ZK_ORIGINAL_REQUEST_URI', $request_uri );
    }
    $path = parse_url($request_uri, PHP_URL_PATH);
    
    $language = zk_detect_language_from_path( $path );
    if ( 'en' !== $language ) {
        $parsed = parse_url( $request_uri );
        $new_path = zk_strip_language_prefix( isset( $parsed['path'] ) ? $parsed['path'] : '/' );
        $new_uri  = $new_path . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
        if ($new_uri === '') $new_uri = '/';
        
        // Rewrite the server variable so WP processes it as the English URL
        $_SERVER['REQUEST_URI'] = $new_uri;
        
        if ( ! defined( 'ZK_REQUEST_LANGUAGE' ) ) {
            define( 'ZK_REQUEST_LANGUAGE', $language );
        }
        if ( 'ka' === $language && ! defined( 'ZK_IS_GEORGIAN_REQUEST' ) ) {
            define( 'ZK_IS_GEORGIAN_REQUEST', true );
        }
    }
}
// Hook early in init
add_action('init', 'zk_early_uri_rewrite', 1);

function zk_get_current_language() {
    if (is_admin()) return 'en';
    if ( defined( 'ZK_REQUEST_LANGUAGE' ) ) {
        return ZK_REQUEST_LANGUAGE;
    }
    if ( defined( 'ZK_IS_GEORGIAN_REQUEST' ) && ZK_IS_GEORGIAN_REQUEST ) {
        return 'ka'; // Backward compatibility for cached/bootstrap integrations.
    }
    return 'en';
}

function zk_disable_canonical_for_ka($redirect_url, $requested_url) {
    if (zk_get_current_language() !== 'en') {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'zk_disable_canonical_for_ka', 10, 2);

/** WordPress' canonical redirect is disabled on translations to retain language.
 * Normalize only the missing slash on an existing translated HTML page.
 */
function zk_redirect_localized_trailing_slash() {
    if ( is_admin() || is_404() || is_feed() || is_preview() || is_search() ||
        ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
        ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ||
        'en' === zk_get_current_language() ) {
        return;
    }
    $uri = defined( 'ZK_ORIGINAL_REQUEST_URI' ) ? ZK_ORIGINAL_REQUEST_URI : '';
    $path = wp_parse_url( $uri, PHP_URL_PATH );
    if ( ! $path || '/' === substr( $path, -1 ) || pathinfo( $path, PATHINFO_EXTENSION ) ||
        'en' === zk_detect_language_from_path( $path ) ||
        ! ( is_front_page() || is_singular() || is_category() || is_tag() || is_tax() || is_post_type_archive() ) ) {
        return;
    }
    $query = wp_parse_url( $uri, PHP_URL_QUERY );
    $destination = home_url( trailingslashit( $path ) ) . ( null !== $query ? '?' . $query : '' );
    wp_safe_redirect( $destination, 301, 'ZK Language Canonical' );
    exit;
}
add_action( 'template_redirect', 'zk_redirect_localized_trailing_slash', 2 );

// --- 3. FRONTEND CONTENT REPLACEMENT ---

function zk_translate_title($title, $post_id = null) {
    if (is_admin() || empty($post_id)) return $title;
    
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $translated_title = get_post_meta( $post_id, zk_language_meta_key( 'title', $language ), true );
        if (!empty($translated_title)) {
            return $translated_title;
        }
    }
    return $title;
}
add_filter('the_title', 'zk_translate_title', 10, 2);

function zk_translate_excerpt($excerpt, $post = null) {
    if (is_admin()) return $excerpt;
    
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $post_id = null;
        if (is_object($post) && isset($post->ID)) {
            $post_id = $post->ID;
        } elseif (is_numeric($post) && (int)$post > 0) {
            $post_id = (int)$post;
        } else {
            $post_id = get_the_ID();
        }
        
        if ($post_id) {
            $translated_excerpt = get_post_meta( $post_id, zk_language_meta_key( 'excerpt', $language ), true );
            if (!empty($translated_excerpt)) {
                return $translated_excerpt;
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
    
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $post_id = null;
        if (is_object($post) && isset($post->ID)) {
            $post_id = $post->ID;
        } elseif (is_numeric($post) && (int)$post > 0) {
            $post_id = (int)$post;
        } else {
            $post_id = get_the_ID();
        }
        
        if ($post_id) {
            $translated_excerpt = get_post_meta( $post_id, zk_language_meta_key( 'excerpt', $language ), true );
            if (!empty($translated_excerpt)) {
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

function zk_translate_content_wrapper($content) {
    if (is_admin()) return $content;
    
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $post_id = get_the_ID();
        $translated_content = get_post_meta( $post_id, zk_language_meta_key( 'content', $language ), true );
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
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $parsed = parse_url($permalink);
        $path = isset($parsed['path']) ? $parsed['path'] : '';

        $language_path = zk_get_language_path( $path, $language );
        if ( ! empty( $path ) && $language_path !== $path ) {
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
            $host   = isset($parsed['host']) ? $parsed['host'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            
            return $scheme . '://' . $host . $port . $language_path . $query;
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
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $definition = zk_get_language( $language );
        return 'lang="' . esc_attr( $definition['locale'] ) . '"';
    }
    return $output;
}
add_filter('language_attributes', 'zk_change_html_lang_ka');

function zk_filter_home_url_ka($url, $path = '', $orig_scheme = null, $blog_id = null) {
    if (is_admin()) return $url;
    $language = zk_get_current_language();
    if ( 'en' !== $language ) {
        $parsed = parse_url($url);
        $p = isset($parsed['path']) ? $parsed['path'] : '';
        $language_path = zk_get_language_path( $p ?: '/', $language );
        if ( $language_path !== $p ) {
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
            $host   = isset($parsed['host']) ? $parsed['host'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            
            return $scheme . '://' . $host . $port . $language_path . $query;
        }
    }
    return $url;
}
add_filter('home_url', 'zk_filter_home_url_ka', 10, 4);

// Send nocache headers on ka requests to prevent browsers/CDNs from caching old 301 redirects
function zk_ka_nocache_headers() {
    if (zk_get_current_language() !== 'en') {
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
        'aubades' => 'გარიჟრაჟები',
        'nocturnes' => 'ნოქტიურნები',

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

/**
 * Default term descriptions in Georgian
 */
function zk_get_default_term_description($slug) {
    static $dict = array(
        // Categories
        'aubades' => 'განთიადისას დაბადებული გამოცხადებები. ზურაბ კოსტავას პირადი ესეები, რომლებიც იჭერენ იმ სიცხადეს, ფილოსოფიურ ძიებებსა და შემოქმედებით ეპიფანიებს, რაც მხოლოდ ალიონზე მოდის.',
        'news' => 'ჰორიზონტს მიღმა: უახლესი მეცნიერება, მომავლის განმსაზღვრელი ტექნოლოგიები და ხელოვნების სამყაროს მუდმივად ცვალებადი პულსი. იყავით ინფორმირებული და წინ უსწრებდეთ დროს.',
        'nocturnes' => 'ჩრდილები, სიჩუმე და ღამისეული ფიქრები. ჩაღრმავება ეგზისტენციალურში, მელანქოლიასა და იმ იდუმალებაში, რომელიც მხოლოდ მაშინ იღვიძებს, როცა სამყარო ჩუმდება.',
        'raw' => 'გაუფილტრავი აზრების ნაკადი და პირველქმნილი ინტელექტუალური ენერგია. ექსპერიმენტული ძიებებისა და კონცეპტუალური ფიქრების კრებული.',
        'reviews' => 'კრიტიკული ლაბორატორია ზურაბ კოსტავას ხელმძღვანელობით. გარღვევის მომტანი სამეცნიერო აღმოჩენებიდან თანამედროვე ხელოვნების ნიუანსებამდე — გაუფილტრავი ანალიზი და პირადი ხედვები იმ ნამუშევრებზე, იდეებსა და ფენომენებზე, რომლებიც ჩვენს რეალობას აყალიბებენ.',

        // Tags
        'art-and-tech' => 'შემოქმედებითი პროცესისა და თანამედროვე ტექნოლოგიების გადაკვეთა. იკვლევს, თუ როგორ აფართოებს ციფრული ხელსაწყოები, პროგრამული უზრუნველყოფა და ინტერაქტიული მედია ტრადიციული ხელოვნების საზღვრებს.',
        'art-tech' => 'შემოქმედებითი პროცესისა და თანამედროვე ტექნოლოგიების გადაკვეთა. იკვლევს, თუ როგორ აფართოებს ციფრული ხელსაწყოები, პროგრამული უზრუნველყოფა და ინტერაქტიული მედია ტრადიციული ხელოვნების საზღვრებს.',
        'artificial-intelligence' => 'ურთიერთქმედება ხელოვნურ ინტელექტთან, ციფრული ცნობიერება და ტექნოლოგიის გავლენა კაცობრიობაზე.',
        'artists-struggle' => 'შემოქმედთა წინაშე არსებული ფიზიკური, მენტალური და შემოქმედებითი დაბრკოლებების კვლევა, და დაუღალავი ბრძოლა ხელოვნების შესაქმნელად ყოველგვარი წინააღმდეგობის მიუხედავად.',
        'audio-tech' => 'ხედვები ჩვენი განვითარების პროცესზე, ვირტუალური ინსტრუმენტების ტექნოლოგიასა და ციფრული ხმის მომავალზე.',
        'authenticity' => 'ფიქრები ნამდვილობაზე, ციფრული ნიღბების მიღმა ცხოვრებაზე, პიროვნულ გულწრფელობასა და ალგორითმებისა და სოციალური წნეხისგან დამოუკიდებელი შემოქმედებითი ხმის პოვნაზე.',
        'behind-the-scenes' => 'მზერა ჩვენს სტუდიაში, კვლევისა და განვითარების (R&D) პროცესსა და პროდუქტების შექმნაზე.',
        'cinematic-narrative' => 'ამბები, სცენარები და კონცეპტუალური ჩანაწერები კინემატოგრაფიული პერსპექტივით, ღრმა ატმოსფეროზე, ტემპსა და ვიზუალურ-ემოციურ სიღრმეზე ფოკუსირებით.',
        'creative-identity' => 'ღრმა ფიქრები შექმნის დაუოკებელ სურვილზე, ფსიქოლოგიურ კავშირზე ხელოვანსა და მის მედიუმს შორის, და გაცნობიერებაზე, რომ ხელოვნება ადამიანის არსებობის განუყოფელი ნაწილია.',
        'creative-process' => 'ხედვები ხელოვანის ბრძოლებსა და გამარჯვებებზე, პროკრასტინაციასთან („პროკისთან“) ჭიდილზე, შთაგონების პოვნასა და შემოქმედების პირველქმნილ რეალობაზე.',
        'digital-art' => 'ვიზუალური ნამუშევრები, რომლებიც აერთიანებს ტრადიციულ კონცეფციებსა და ციფრულ ხელსაწყოებს.',
        'digital-manifesto' => 'საჯარო დეკლარაციები, შემოქმედებითი ფილოსოფია და გაუფილტრავი განაცხადები ციფრული არსებობის, თვითგამოხატვისა და პირადი რევოლუციების შესახებ.',
        'entrepreneurship' => 'კომპანიის შენების გზა — საწყისი ხედვიდან გლობალური მასშტაბირების გამოწვევებამდე.',
        'everyday-observations' => 'ღრმა არსის, სილამაზის ან იუმორის პოვნა ყოველდღიურ, ერთი შეხედვით ჩვეულებრივ სიტუაციებში, ქუჩის შეხვედრებში, მცირე დეტალებსა და ბუნებაში.',
        'existentialism' => 'ღრმა ფილოსოფიური ფიქრები სიცოცხლის არსზე, სიკვდილზე, სამყაროზე, სიმულაციის თეორიასა და ჩვენს ადგილზე კოსმოსში.',
        'generative-art' => 'ხელოვნება, რომელიც სრულად ან ნაწილობრივ შექმნილია ავტონომიური სისტემების მიერ. ის ეყრდნობა ალგორითმებს, კოდსა და მათემატიკას დინამიკური და არაპროგნოზირებადი ვიზუალური ფორმების შესაქმნელად.',
        'georgian-heritage' => 'ხიდის გადება უძველეს მუსიკალურ ტრადიციებსა და თანამედროვე ციფრულ ინოვაციებს შორის.',
        'human-connection' => 'დაკვირვებები სიყვარულზე, ემპათიაზე, ურთიერთობებსა და ადამიანური ურთიერთქმედების სირთულეებზე.',
        'humor-and-satire' => 'თვითირონია, სარკაზმი და ყოველდღიური ცხოვრების აბსურდულად სასაცილო მხარეების აღმოჩენა.',
        'kostava-creative' => 'ჩვენი აუდიო-ვიზუალური ეკოსისტემის მთავარი სიახლეები, განახლებები და ეტაპები.',
        'making-music' => 'ჩაღრმავება მუსიკის შექმნის პროცესში — არა მხოლოდ ტექნიკური ასპექტების, არამედ მისი მდიდარი ფილოსოფიური განზომილებების კვლევა.',
        'melancholy-loneliness' => 'შემოქმედებითი და ფილოსოფიური ნამუშევრები, რომლებიც ასახავს იზოლაციის პირველქმნილ ანატომიას, სამყაროსგან მიტოვებულობასა და ღრმა ემოციურ ნოსტალგიას.',
        'mental-health' => 'გულწრფელი, გაუფილტრავი საუბრები ზედმეტ ფიქრზე, შფოთვაზე, მარტოობაზე, დეპრესიასა და წერისა თუ იუმორის დამცავ მექანიზმად გამოყენებაზე.',
        'multimedia' => 'პირდაპირი, გაუფილტრავი კადრები სტუდიიდან და ყოველდღიური შემოქმედებითი რუტინიდან.',
        'new-beginings' => 'ფიქრები ახალი თავების დაწყებაზე, ცხოვრებისეული გარდამავალი ეტაპების მიღებაზე, პიროვნულ ტრანსფორმაციასა და მომავლისკენ თამამი ნაბიჯების გადადგმაზე.',
        'new-beginnings' => 'ფიქრები ახალი თავების დაწყებაზე, ცხოვრებისეული გარდამავალი ეტაპების მიღებაზე, პიროვნულ ტრანსფორმაციასა და მომავლისკენ თამამი ნაბიჯების გადადგმაზე.',
        'nostalgia' => 'ფიქრები წარსულზე, ბავშვობის მოგონებებსა და იმ სევდანარევ კავშირზე, რომელიც გარდასულ ეპოქებსა და აწმყოს რეალობას შორის არსებობს.',
        'parenthood' => 'ფიქრები მშობლობის გზაზე, ბავშვის ზრდასა და იმ ცხოვრებისეულ გაკვეთილებზე, რომლებიც მამობამ მომიტანა.',
        'personal-essay' => 'პირდაპირი, გაუფილტრავი, ავტობიოგრაფიული ჩანაწერები, რომლებიც ასახავს ხელოვანის ცხოვრების კონკრეტულ მოვლენებს, ბრძოლებსა და პირად გარდამტეხ მომენტებს.',
        'photography-philosophy' => 'ღრმა ფიქრები ვიზუალურ ხელოვნებაზე, კადრის დაჭერის ფსიქოლოგიაზე, დროის გაჩერებასა და იმ ემოციურ სიმძიმეზე, რომელიც ობიექტივის მიღმა დგას.',
        'reading' => 'ფიქრები წაკითხულ წიგნებზე, ლიტერატურულ შთაგონებებსა და ისტორიებზე, რომლებმაც ჩემი მსოფლმხედველობა და პირადი გზა ჩამოაყალიბეს.',
        'self-discovery' => 'ფიქრები საკუთარი ჭეშმარიტი იდენტობის პოვნაზე, თვითმარქვიას სინდრომის დაძლევაზე, სოციალური ჩარჩოების დამსხვრევასა და პიროვნულ ზრდაზე პირდაპირი ინტროსპექციის გზით.',
        'simulation-theory' => 'ფილოსოფიური და შემოქმედებითი ძიებები რეალობის, როგორც ციფრული სიმულაციის შესახებ, ნეირონულ ქსელებსა და ზღვარზე ტექნოლოგიასა და ადამიანის ცნობიერებას შორის.',
        'storytelling' => 'სამყაროების შენების ხელოვნება, თხრობა და აბსტრაქტული ფიქრების სიტყვებად გარდაქმნა.',
        'urban-vignettes' => 'მოკლე, ცოცხალი და ღრმად დაკვირვებული ისტორიები, დაჭერილი ქალაქის ქაოსში, თანამედროვე რუტინასა და მოულოდნელ ქუჩის შეხვედრებში.',
        'vusual-language' => 'საკომუნიკაციო სისტემა, რომელიც აზრს გადმოსცემს ვიზუალური ელემენტებით — ფერებით, ფორმებით, ხაზებითა და ტიპოგრაფიით — სიტყვიერ გამოხატვაზე დაყრდნობის გარეშე.',
        'visual-language' => 'საკომუნიკაციო სისტემა, რომელიც აზრს გადმოსცემს ვიზუალური ელემენტებით — ფერებით, ფორმებით, ხაზებითა და ტიპოგრაფიით — სიტყვიერ გამოხატვაზე დაყრდნობის გარეშე.',
    );
    $slug_clean = strtolower(trim((string)$slug));
    return isset($dict[$slug_clean]) ? $dict[$slug_clean] : '';
}

function zk_get_translated_term_description($term) {
    if (empty($term)) return '';
    if (is_numeric($term)) {
        $term = get_term((int)$term);
    }
    if (!is_object($term) || is_wp_error($term)) return '';

    if (function_exists('zk_get_current_language') && zk_get_current_language() === 'ka') {
        $meta_desc = get_term_meta($term->term_id, '_zk_description_ka', true);
        if (!empty($meta_desc)) {
            return $meta_desc;
        }
        $default_desc = zk_get_default_term_description($term->slug);
        if (!empty($default_desc)) {
            return $default_desc;
        }
    }
    return $term->description;
}

function zk_taxonomy_add_custom_fields($taxonomy) {
    foreach ( zk_get_translatable_languages( false ) as $code => $language ) {
        echo '<div class="form-field term-group"><label for="zk_name_' . esc_attr( $code ) . '"><strong>' . esc_html( $language['native_name'] ) . ' name</strong></label><input type="text" id="zk_name_' . esc_attr( $code ) . '" name="zk_name_' . esc_attr( $code ) . '" value=""><p class="description">Translated category/tag name (' . esc_html( strtoupper( $code ) ) . ').</p></div>';
        echo '<div class="form-field term-group"><label for="zk_description_' . esc_attr( $code ) . '"><strong>' . esc_html( $language['native_name'] ) . ' description</strong></label><textarea id="zk_description_' . esc_attr( $code ) . '" name="zk_description_' . esc_attr( $code ) . '" rows="5" cols="50"></textarea></div>';
    }
}

function zk_taxonomy_edit_custom_fields($term, $taxonomy) {
    foreach ( zk_get_translatable_languages( false ) as $code => $language ) {
        $name = get_term_meta( $term->term_id, zk_language_meta_key( 'name', $code ), true );
        $desc = get_term_meta( $term->term_id, zk_language_meta_key( 'description', $code ), true );
        if ( 'ka' === $code && empty( $name ) ) $name = zk_get_default_term_translation( $term->slug );
        if ( 'ka' === $code && empty( $desc ) ) $desc = zk_get_default_term_description( $term->slug );
        echo '<tr class="form-field term-group-wrap"><th scope="row"><label for="zk_name_' . esc_attr( $code ) . '">' . esc_html( $language['native_name'] ) . ' name</label></th><td><input type="text" id="zk_name_' . esc_attr( $code ) . '" name="zk_name_' . esc_attr( $code ) . '" value="' . esc_attr( $name ) . '" style="width:100%;max-width:500px"><p class="description">Translated category/tag name (' . esc_html( strtoupper( $code ) ) . ').</p></td></tr>';
        echo '<tr class="form-field term-group-wrap"><th scope="row"><label for="zk_description_' . esc_attr( $code ) . '">' . esc_html( $language['native_name'] ) . ' description</label></th><td><textarea id="zk_description_' . esc_attr( $code ) . '" name="zk_description_' . esc_attr( $code ) . '" rows="6" style="width:100%;max-width:500px">' . esc_textarea( $desc ) . '</textarea></td></tr>';
    }
}

function zk_save_taxonomy_custom_fields($term_id) {
    foreach ( zk_get_translatable_languages( false ) as $code => $language ) {
        $name_key = 'zk_name_' . $code;
        $desc_key = 'zk_description_' . $code;
        if ( isset( $_POST[ $name_key ] ) ) {
            update_term_meta( $term_id, zk_language_meta_key( 'name', $code ), sanitize_text_field( wp_unslash( $_POST[ $name_key ] ) ) );
        }
        if ( isset( $_POST[ $desc_key ] ) ) {
            update_term_meta( $term_id, zk_language_meta_key( 'description', $code ), sanitize_textarea_field( wp_unslash( $_POST[ $desc_key ] ) ) );
        }
    }
}

function zk_taxonomy_columns($columns) {
    foreach ( zk_get_translatable_languages() as $code => $language ) {
        $columns['zk_name_' . $code] = strtoupper( $code ) . ' name';
    }
    return $columns;
}

function zk_taxonomy_custom_column($content, $column_name, $term_id) {
    if ( 0 === strpos( $column_name, 'zk_name_' ) ) {
        $code = substr( $column_name, 8 );
        $term = get_term($term_id);
        $val = get_term_meta( $term_id, zk_language_meta_key( 'name', $code ), true );
        if ( 'ka' === $code && empty($val) && $term && !is_wp_error($term)) {
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

// Auto-sync terms with default Georgian names and descriptions if not yet saved in DB
function zk_sync_term_descriptions_ka() {
    if (get_option('zk_term_translations_seeded_v1', false)) return;

    static $synced = false;
    if ($synced) return;
    $synced = true;

    $terms = get_terms(array(
        'taxonomy'   => array('category', 'post_tag'),
        'hide_empty' => false,
    ));
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $existing_desc = get_term_meta($term->term_id, '_zk_description_ka', true);
            if (empty($existing_desc)) {
                $default_desc = zk_get_default_term_description($term->slug);
                if (!empty($default_desc)) {
                    update_term_meta($term->term_id, '_zk_description_ka', $default_desc);
                }
            }
            $existing_name = get_term_meta($term->term_id, '_zk_name_ka', true);
            if (empty($existing_name)) {
                $default_name = zk_get_default_term_translation($term->slug);
                if (!empty($default_name)) {
                    update_term_meta($term->term_id, '_zk_name_ka', $default_name);
                }
            }
        }
    }

    update_option('zk_term_translations_seeded_v1', true, false);
}
add_action('init', 'zk_sync_term_descriptions_ka', 20);

// Update only the two legacy series names that were previously seeded. Custom
// taxonomy translations remain untouched and future edits flow to every UI
// surface that reads the translated term name, including blog filter pills.
function zk_migrate_blog_series_names_ka_v2() {
    if ( get_option( 'zk_blog_series_names_migrated_v2', false ) ) return;

    $renames = array(
        'aubades' => array(
            'name'   => 'გარიჟრაჟები',
            'legacy' => array( '', 'დილისპირულები', 'დილის სიმღერები' ),
        ),
        'nocturnes' => array(
            'name'   => 'ნოქტიურნები',
            'legacy' => array( '', 'ძილისპირულები' ),
        ),
    );

    foreach ( $renames as $slug => $rename ) {
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term || is_wp_error( $term ) ) continue;

        $current = trim( (string) get_term_meta( $term->term_id, '_zk_name_ka', true ) );
        if ( in_array( $current, $rename['legacy'], true ) ) {
            update_term_meta( $term->term_id, '_zk_name_ka', $rename['name'] );
        }
    }

    update_option( 'zk_blog_series_names_migrated_v2', true, false );
}
add_action( 'init', 'zk_migrate_blog_series_names_ka_v2', 21 );

// Frontend term translation filters
function zk_filter_get_term($term, $taxonomy = '') {
    if (is_admin()) return $term;
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    if ( 'en' !== $language ) {
        if (is_object($term) && isset($term->term_id)) {
            $trans = get_term_meta( $term->term_id, zk_language_meta_key( 'name', $language ), true );
            if ( 'ka' === $language && empty($trans) && isset($term->slug)) {
                $trans = zk_get_default_term_translation($term->slug);
            }
            if (!empty($trans)) {
                $term->name = $trans;
            }
            $desc_trans = get_term_meta( $term->term_id, zk_language_meta_key( 'description', $language ), true );
            if ( 'ka' === $language && empty($desc_trans) && isset($term->slug)) {
                $desc_trans = zk_get_default_term_description($term->slug);
            }
            if (!empty($desc_trans)) {
                $term->description = $desc_trans;
            }
        }
    }
    return $term;
}
add_filter('get_term', 'zk_filter_get_term', 10, 2);

function zk_filter_single_term_title($title) {
    if (is_admin()) return $title;
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    if ( 'en' !== $language ) {
        $obj = get_queried_object();
        if ($obj && isset($obj->term_id)) {
            $trans = get_term_meta( $obj->term_id, zk_language_meta_key( 'name', $language ), true );
            if ( 'ka' === $language && empty($trans) && isset($obj->slug)) {
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

function zk_filter_archive_description_ka($description) {
    if (is_admin()) return $description;
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    if ( 'en' !== $language ) {
        $term = get_queried_object();
        if ($term && !is_wp_error($term) && isset($term->term_id)) {
            $translated = get_term_meta( $term->term_id, zk_language_meta_key( 'description', $language ), true );
            if ( 'ka' === $language && empty( $translated ) ) $translated = zk_get_default_term_description( $term->slug );
            if (!empty($translated)) {
                return wpautop(wptexturize($translated));
            }
        }
    }
    return $description;
}
add_filter('get_the_archive_description', 'zk_filter_archive_description_ka', 20);
add_filter('the_archive_description', 'zk_filter_archive_description_ka', 20);

function zk_filter_term_description_ka($description, $term_id = 0, $taxonomy = '') {
    if (is_admin()) return $description;
    $language = function_exists( 'zk_get_current_language' ) ? zk_get_current_language() : 'en';
    if ( 'en' !== $language ) {
        $term = $term_id ? get_term($term_id, $taxonomy) : get_queried_object();
        if ($term && !is_wp_error($term) && isset($term->term_id)) {
            $translated = get_term_meta( $term->term_id, zk_language_meta_key( 'description', $language ), true );
            if ( 'ka' === $language && empty( $translated ) ) $translated = zk_get_default_term_description( $term->slug );
            if (!empty($translated)) {
                return $translated;
            }
        }
    }
    return $description;
}
add_filter('term_description', 'zk_filter_term_description_ka', 20, 3);

// --- 5. LANGUAGE SWITCHER (TOP RIGHT HEADER) ---

function zk_get_language_switcher_urls() {
    $current_lang = function_exists('zk_get_current_language') ? zk_get_current_language() : 'en';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $parsed = parse_url($request_uri);
    $path = isset($parsed['path']) ? $parsed['path'] : '/';
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

    $base_path = zk_strip_language_prefix( $path );

    $home_url = untrailingslashit(get_option('home'));
    if (empty($home_url)) {
        $home_url = 'https://zurabkostava.com';
    }

    $result = array( 'current' => $current_lang, 'languages' => array() );
    foreach ( zk_get_languages() as $code => $language ) {
        $route = zk_get_language_path( $base_path, $code ) . $query;
        $result['languages'][ $code ] = array_merge( $language, array(
            'url'   => $home_url . $route,
            'route' => $route,
        ) );
        // Preserve the old array shape for code that still reads EN/KA keys.
        $result[ $code . '_url' ]   = $home_url . $route;
        $result[ $code . '_route' ] = $route;
    }
    return $result;
}

function zk_render_language_switcher() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;

    $data = zk_get_language_switcher_urls();
    $current = $data['current'];
    ?>
    <div class="zk-lang-switcher" role="navigation" aria-label="Language selector">
        <?php $index = 0; foreach ( $data['languages'] as $code => $language ) : ?>
            <?php if ( $index++ ) : ?><span class="zk-lang-divider" aria-hidden="true"></span><?php endif; ?>
            <a href="<?php echo esc_url( $language['url'] ); ?>"
               data-route="<?php echo esc_attr( $language['route'] ); ?>"
               class="zk-lang-btn <?php echo ( $current === $code ) ? 'is-active' : ''; ?>"
               aria-label="<?php echo esc_attr( $language['native_name'] . ' language' ); ?>"
               <?php echo ( $current === $code ) ? 'aria-current="true"' : ''; ?>>
                <span><?php echo esc_html( strtoupper( $code ) ); ?></span>
            </a>
        <?php endforeach; ?>
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

