const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('browser-accessible maintenance backdoors stay disabled', () => {
    const functions = read('functions.php');
    assert.doesNotMatch(functions, /\$_GET\s*\[\s*['"]zk_flush['"]\s*\]/);

    for (const file of ['dump_rules.php', 'extract.php']) {
        const source = read(file);
        assert.match(source, /PHP_SAPI\s*!==\s*['"]cli['"]/);
        assert.match(source, /http_response_code\(\s*404\s*\)/);
    }
});

test('public OG lookup only fetches approved HTTPS sources safely', () => {
    const source = read('functions.php');
    const security = read('inc/platform/security.php');
    assert.match(security, /function zk_is_allowed_og_source_url/);
    assert.match(security, /'https'\s*!==\s*\$scheme/);
    assert.match(source, /wp_safe_remote_get\(\s*\$url/);
    assert.match(source, /zk_request_within_rate_limit\(\s*'og-image'/);
    assert.doesNotMatch(source, /wp_ajax_nopriv_zk_cache_og_image/);
});

test('analytics mutations are tied to the originating visitor and session', () => {
    const source = read('functions.php');
    const security = read('inc/platform/security.php');
    assert.match(source, /array\('id' => \$view_id, 'visitor_id' => \$visitor_id, 'session_id' => \$session_id\)/);
    assert.match(source, /zk_request_within_rate_limit\(\s*'analytics'/);
    assert.doesNotMatch(security, /HTTP_X_FORWARDED_FOR/);
    assert.doesNotMatch(security, /HTTP_CLIENT_IP/);
});

test('SPA language follows the destination URL and the language registry', () => {
    const source = read('app.js');
    assert.match(source, /window\.ZK\.languages/);
    assert.match(source, /document\.documentElement\.lang = nextLocale/);
});

test('language registry preserves English and Georgian while supporting future languages', () => {
    const registry = read('inc/platform/languages.php');
    const center = read('inc/platform/language-center.php');
    const manager = read('inc/language-manager.php');

    assert.match(registry, /function zk_get_languages/);
    assert.match(registry, /function zk_detect_language_from_path/);
    assert.match(registry, /function zk_get_language_path/);
    assert.match(center, /Language Center/);
    assert.match(center, /check_admin_referer\( 'zk_save_languages'/);
    assert.match(manager, /zk_get_translatable_languages\( false \)/);
    assert.match(manager, /zk_language_meta_key\( 'content', \$language \)/);
});

test('translation coverage ignores shortcode-only content and audits SEO/GEO translations', () => {
    const center = read('inc/platform/language-center.php');

    assert.match(center, /function zk_language_center_has_translatable_content/);
    assert.match(center, /strip_shortcodes\( \(string\) \$content \)/);
    assert.match(center, /\$required\['excerpt'\] = zk_language_meta_key\( 'excerpt', \$code \)/);
    assert.match(center, /if \( \$has_translatable_content \)/);
    assert.match(center, /'SEO title'\s*=>\s*'seo_title'/);
    assert.match(center, /'SEO description'\s*=>\s*'seo_description'/);
    assert.match(center, /'GEO summary'\s*=>\s*'geo_ai_summary'/);
    assert.match(center, /'GEO FAQ'\s*=>\s*'geo_faq'/);
    assert.match(center, /zk_language_meta_key\( \$field, \$code \)/);
});

test('renamed Georgian blog series flow from taxonomy translations into filters', () => {
    const functions = read('functions.php');
    const manager = read('inc/language-manager.php');
    const index = read('index.php');

    assert.match(manager, /'aubades'\s*=>\s*'გარიჟრაჟები'/);
    assert.match(manager, /'nocturnes'\s*=>\s*'ნოქტიურნები'/);
    assert.match(manager, /function zk_migrate_blog_series_names_ka_v2/);
    assert.match(manager, /in_array\( \$current, \$rename\['legacy'\], true \)/);
    assert.match(functions, /'aubades'\s*=>\s*'გარიჟრაჟები'/);
    assert.match(functions, /'nocturnes'\s*=>\s*'ნოქტიურნები'/);
    assert.match(index, /'aubades': 'ᲒᲐᲠᲘᲟᲠᲐᲟᲔᲑᲘ'/);
    assert.match(index, /'nocturnes': 'ᲜᲝᲥᲢᲘᲣᲠᲜᲔᲑᲘ'/);
});

test('photography filters follow the current language without sharing cached markup', () => {
    const source = read('functions.php');
    const center = read('inc/platform/language-center.php');

    assert.match(source, /function zk_gallery_labels/);
    assert.match(source, /'all'\s*=>\s*'ყველა'/);
    assert.match(source, /'camera'\s*=>\s*'კამერა'/);
    assert.match(source, /'mobile'\s*=>\s*'მობილური'/);
    assert.match(source, /zk_gallery_html_v6_.*sanitize_key\( \$language \)/);
    assert.match(source, /esc_html\( \$labels\['all'\] \)/);
    assert.match(source, /get_option\( 'zk_gallery_labels_v1'/);
    assert.match(center, /admin_post_zk_save_gallery_labels/);
    assert.match(center, /check_admin_referer\( 'zk_save_gallery_labels'/);
    assert.match(center, /Photography filter tabs/);
    assert.match(center, /zk_flush_gallery_cache\(\)/);
});

test('first-party assets use file modification versions instead of request time', () => {
    const sources = [
        read('inc/platform/assets.php'),
        read('functions.php'),
        read('page-instavery.php'),
        read('page-reader.php'),
        read('page-wordevo.php'),
    ].join('\n');

    assert.match(sources, /function zk_asset_version/);
    assert.doesNotMatch(sources, /\?v=<\?php echo time\(\)/);
    assert.doesNotMatch(sources, /wp_enqueue_(?:style|script)\([^\n]+time\(\)/);
});
