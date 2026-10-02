<?php
// Render the actual schema generator for both language requests without WordPress.
$language = 'en';
$custom_meta = true;
function zk_get_clean_bilingual_urls() { global $language; return ['current' => $language, 'canonical_url' => home_url('/')]; }
function zk_get_language($code) { return ['locale' => $code === 'ka' ? 'ka-GE' : 'en-US']; }
function zk_get_languages() { return ['en' => zk_get_language('en'), 'ka' => zk_get_language('ka')]; }
function get_bloginfo($key) { return $key === 'name' ? 'Zurab Kostava' : 'Artist & composer'; }
function home_url($path) { global $language; return 'https://example.com' . ($language === 'ka' ? '/ka' : '') . $path; }
function get_option($key, $default = '') { return $key === 'home' ? 'https://example.com' : $default; }
function trailingslashit($url) { return rtrim($url, '/') . '/'; }
function esc_url_raw($url) { return $url; }
function esc_url($url) { return $url; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function is_page($slug = null) { return $slug === null; }
function is_front_page() { return true; }
function is_home() { return false; }
function is_singular($type = null) { return $type === null; }
function get_page_template_slug($id) { return ''; }
function get_queried_object_id() { return 10; }
function zk_get_localized_post_meta($id, $field, $code) {
    global $custom_meta;
    if (!$custom_meta) return '';
    return $field === 'seo_title' ? ($code === 'ka' ? 'ზურაბ კოსტავა | ხელოვნება' : 'Zurab Kostava | Sound & Visual') : "Artist's digital space";
}
function verify($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$source = file_get_contents(__DIR__ . '/../functions.php');
foreach (['zk_render_json_ld_schema', 'zk_inject_faq_schema'] as $function) {
    $start = strpos($source, "function $function() {");
    $end = strpos($source, "add_action( 'wp_head', '$function'", $start);
    eval(substr($source, $start, $end - $start));
}
foreach (['en', 'ka'] as $language) {
    foreach ([true, false] as $custom_meta) {
        ob_start(); zk_render_json_ld_schema(); $html = ob_get_clean();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $entities = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $by_type = array_column($entities, null, '@type');
        verify(count($entities) === 3, 'Homepage has exactly Person, WebSite and WebPage');
        $site = $by_type['WebSite']; $page = $by_type['WebPage']; $person = $by_type['Person'];
        verify($site['url'] === 'https://example.com/' && $site['@id'] === 'https://example.com/#website', 'Language-filtered home_url must not split the website entity');
        verify($site['inLanguage'] === ['en-US', 'ka-GE'], 'Site languages come from enabled registry');
        verify($page['url'] === home_url('/') && $page['@id'] === home_url('/') . '#webpage', 'Each translation has its own page URL and ID');
        verify($page['isPartOf']['@id'] === $site['@id'] && $page['mainEntity']['@id'] === $person['@id'], 'Page links to the same site and person');
        verify($page['inLanguage'] === zk_get_language($language)['locale'], 'Localized page language');
        verify($page['name'] === ($custom_meta ? zk_get_localized_post_meta(10, 'seo_title', $language) : ($language === 'ka' ? 'ზურაბ კოსტავა — ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი' : 'Zurab Kostava — Artist & composer')), 'Use editor title or localized SEO fallback');
        verify($page['description'] === ($custom_meta ? "Artist's digital space" : ($language === 'ka' ? 'ქართველი მულტიდისციპლინური ხელოვანი, კომპოზიტორი და დიზაინერი' : 'Artist & composer')), 'Preserve description and punctuation without HTML entity encoding');
        ob_start(); zk_inject_faq_schema(); $faq = ob_get_clean();
        verify($faq === '', 'Homepage must not emit invisible FAQ content');
    }
}
echo "PASS homepage schemas: EN/KA, shared entities, localized metadata and fallbacks, no invisible FAQ\n";
