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
    assert.match(center, /zk_language_center_has_translatable_content\( \$post->post_content \)/);
    assert.match(center, /'SEO title'\s*=>\s*'seo_title'/);
    assert.match(center, /'SEO description'\s*=>\s*'seo_description'/);
    assert.match(center, /'GEO summary'\s*=>\s*'geo_ai_summary'/);
    assert.match(center, /'GEO FAQ'\s*=>\s*'geo_faq'/);
    assert.match(center, /zk_language_meta_key\( \$field, \$code \)/);
});

test('multilingual sitemap only advertises complete translations', () => {
    const source = read('functions.php');
    const center = read('inc/platform/language-center.php');

    assert.match(center, /function zk_is_post_translation_complete/);
    assert.match(center, /function zk_is_term_translation_complete/);
    assert.match(center, /in_array\( \$code, array\( 'en', 'ka' \), true \)/);
    assert.match(source, /function zk_is_language_version_indexable/);
    assert.match(source, /if \( ! zk_is_language_version_indexable\( \$code \) \) continue/);
    assert.match(source, /zk_is_post_translation_complete\( \$post_id, \$code \)/);
    assert.match(source, /zk_is_term_translation_complete\( \$term, \$code \)/);
    assert.match(source, /'taxonomy' => array\( 'category', 'post_tag' \)/);
    assert.match(source, /hreflang=\\"x-default\\"/);
    assert.match(source, /X-Robots-Tag: noindex, follow/);
    assert.match(source, /\$thin_archive \|\| ! zk_is_language_version_indexable\( \$language \)/);
    assert.match(source, /if \( ! is_404\(\) \) \{\s*\/\/ Canonical URL/);
});

test('SEO and GEO fields follow every registered language', () => {
    const source = read('functions.php');

    assert.match(source, /function zk_localized_meta_key/);
    assert.match(source, /function zk_get_localized_post_meta/);
    assert.match(source, /function zk_get_localized_term_meta/);
    assert.match(source, /foreach \( zk_get_languages\( false \) as \$code => \$language \)/);
    assert.match(source, /zk_localized_meta_key\( 'seo_title', \$code \)/);
    assert.match(source, /zk_localized_meta_key\( \$field, \$code \)/);
    assert.match(source, /zk_get_localized_post_meta\( \$target_id, 'geo_ai_summary', \$language, true \)/);
    assert.match(source, /zk_get_localized_post_meta\( \$target_id, 'geo_faq', \$language \)/);
    assert.match(source, /zk_get_localized_term_meta\( \$term_id, 'geo_faq', \$language \)/);
    assert.doesNotMatch(source, /zk_get_localized_post_meta\( \$target_id, 'geo_faq', \$language, true \)/);
    assert.match(source, /str_replace\( '-', '_', \$language_info\['locale'\] \)/);
    assert.match(source, /og:locale:alternate/);
});

test('person identity stays canonical and unambiguous across languages', () => {
    const source = read('functions.php');

    assert.match(source, /\$identity_url = trailingslashit\( esc_url_raw\( \(string\) get_option\( 'home'/);
    assert.match(source, /\$person_id\s*=\s*\$identity_url \. '#person'/);
    assert.match(source, /'@id' => \$person_id/);
    assert.match(source, /'disambiguatingDescription'/);
    assert.match(source, /'givenName'/);
    assert.match(source, /'familyName'/);
    assert.match(source, /\$ambiguous_aliases = \[ 'zurab', 'kostava', 'ზურაბ', 'კოსტავა' \]/);
    assert.match(source, /zk_social_behance/);
    assert.match(source, /zk_social_musicbrainz/);
    assert.match(source, /musicbrainz\.org\/artist\/61081717-65c9-4717-9683-9ca286ae30e7/);
});

test('instant indexing can submit every strictly completed translation', () => {
    const indexing = read('inc/zk-indexing-api.php');

    assert.match(indexing, /function zk_indexing_translation_is_complete/);
    assert.match(indexing, /zk_language_translation_post_requirements\( \$post, \$code \)/);
    assert.match(indexing, /function zk_indexing_completed_translation_urls/);
    assert.match(indexing, /zk_get_translatable_languages\(\)/);
    assert.match(indexing, /zk_get_language_path\( \$path, \$code \)/);
    assert.match(indexing, /name="zk_submit_completed_translations"/);
    assert.match(indexing, /Submit all completed translations/);
    assert.match(indexing, /Its private contents are hidden for security/);
    assert.doesNotMatch(indexing, /esc_textarea\(\$key\)/);
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

test('galaxy optimization preserves the complete visual density', () => {
    const galaxy = read('galaxy-bg.js');

    assert.match(galaxy, /const starCount = 400000/);
    assert.match(galaxy, /const giantNebulaStarCount = 80000/);
    assert.match(galaxy, /starSystem3 = new THREE\.Points/);
    assert.match(galaxy, /heroSystem3 = new THREE\.Points/);
    assert.match(galaxy, /fillInResponsiveChunks\(starCount, 2500/);
    assert.match(galaxy, /fillInResponsiveChunks\(giantNebulaStarCount, 1000/);
    assert.match(galaxy, /if \(document\.hidden\) return/);
});

test('heavy visual assets do not block the first page paint', () => {
    const source = read('functions.php');

    assert.match(source, /foreach \( array\( 'three-js', 'zk-galaxy-bg', 'zk-analytics', 'zk-app' \) as \$deferred_handle \)/);
    assert.match(source, /wp_script_add_data\( \$deferred_handle, 'strategy', 'defer' \)/);
    assert.match(source, /\$attributes \.= 'defer '/);
    assert.match(source, /if \( 'zk-fonts' !== \$handle \)/);
    assert.match(source, /media=\$1print\$1 onload=/);
    assert.match(source, /<noscript>/);
});

test('SPA language changes preserve the running galaxy canvas', () => {
    const app = read('app.js');

    assert.match(app, /var persistentGalaxyCanvas = document\.getElementById\('zk-galaxy-canvas'\)/);
    assert.match(app, /var replacementGalaxyCanvas = viewEl\.querySelector\('#zk-galaxy-canvas'\)/);
    assert.match(app, /replacementGalaxyCanvas\.replaceWith\(persistentGalaxyCanvas\)/);
});

test('mobile archive filters scroll inside the viewport', () => {
    const css = read('style.css');
    const app = read('app.js');

    assert.match(css, /\.zk-grid-wrapper \{ width: 100%; max-width: 100%; min-width: 0; \}/);
    assert.match(css, /\.zk-category-filters-wrapper[\s\S]*?max-width: 100%;[\s\S]*?overflow: hidden;/);
    assert.match(css, /\.zk-category-filters[\s\S]*?overflow-x: auto;[\s\S]*?width: 100%;[\s\S]*?min-width: 0;/);
    assert.match(css, /\.zk-filter-pill[\s\S]*?flex: 0 0 auto;/);
    assert.doesNotMatch(app, /controls\.style\.flexWrap/);
});

test('shared cinematic gallery preloads responsive decoded neighbor images', () => {
    const functions = read('functions.php');
    const app = read('app.js');

    assert.match(functions, /function zk_gallery_lightbox_source_attributes/);
    assert.match(functions, /'data-full-srcset'/);
    assert.match(functions, /'data-full-sizes'\s*=>\s*'90vw'/);
    assert.match(app, /var imageCache = new Map\(\)/);
    assert.match(app, /function preloadImage\(img, priority\)/);
    assert.match(app, /preloader\.decode/);
    assert.match(app, /function warmAround\(position, includeCurrent\)/);
    assert.match(app, /grid\.addEventListener\('pointerover'/);
    assert.match(app, /rootMargin: '350px 0px'/);
    assert.match(app, /warmAround\(index, false\)/);
});

test('opened gallery photos support deliberate mobile swipe navigation', () => {
    const app = read('app.js');
    const css = read('style.css');

    assert.match(app, /lightbox\.addEventListener\('touchstart'/);
    assert.match(app, /lightbox\.addEventListener\('touchend'/);
    assert.match(app, /Math\.abs\(dx\) <= Math\.abs\(dy\) \* 1\.15/);
    assert.match(app, /if \(dx < 0\) next\(\)/);
    assert.match(app, /else prev\(\)/);
    assert.match(app, /\.zk-lightbox-thumbs, button, a/);
    assert.match(css, /\.zk-lightbox \{[\s\S]*?touch-action: pan-y pinch-zoom;/);
    assert.match(css, /\.zk-lightbox-thumbs \{[\s\S]*?touch-action: pan-x;/);
});

test('SPA navigation carries WordPress block layout styles before rendering content', () => {
    const app = read('app.js');

    assert.match(app, /style\[id\^="wp-block-"\]\[id\$="-inline-css"\]/);
    assert.match(app, /style#core-block-supports-inline-css/);
    assert.match(app, /function collectBlockStyles\(doc\)/);
    assert.match(app, /function syncBlockStyles\(styles\)/);
    assert.match(app, /blockStyles: blockStyles/);
    assert.match(app, /blockStyles: initialBlockStyles/);
    assert.match(app, /syncBlockStyles\(data\.blockStyles\);\s*viewEl\.innerHTML = data\.html;/);
});
