<?php
// Standalone request-level regression harness; no WordPress installation needed.
$cases = array(
    array('/ka', 'GET', false, 'ka', 'https://zurabkostava.com/ka/'),
    array('/ka/books/beta?ref=share', 'GET', false, 'ka', 'https://zurabkostava.com/ka/books/beta/?ref=share'),
    array('/de/about', 'HEAD', false, 'de', 'https://zurabkostava.com/de/about/'),
    array('/ka/about/', 'GET', false, 'ka', null),
    array('/ka/missing', 'GET', true, 'ka', null),
    array('/ka/about', 'POST', false, 'ka', null),
    array('/ka/data.json', 'GET', false, 'ka', null),
    array('/about', 'GET', false, 'en', null),
);
$case = $cases[(int)($argv[1] ?? 0)];
define('ZK_ORIGINAL_REQUEST_URI', $case[0]);
$_SERVER['REQUEST_METHOD'] = $case[1];
// Simulate the URI already rewritten by the language manager.
$_SERVER['REQUEST_URI'] = '/books/beta?ref=share';
function is_admin() { return false; }
function is_404() { global $case; return $case[2]; }
function is_feed() { return false; }
function is_preview() { return false; }
function is_search() { return false; }
function is_front_page() { return false; }
function is_singular() { return true; }
function is_category() { return false; }
function is_tag() { return false; }
function is_tax() { return false; }
function is_post_type_archive() { return false; }
function zk_get_current_language() { global $case; return $case[3]; }
function zk_detect_language_from_path($path) { return preg_match('#^/(ka|de)(/|$)#', $path, $m) ? $m[1] : 'en'; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
function home_url($path) { return 'https://zurabkostava.com' . $path; }
function wp_safe_redirect($destination, $status, $source) {
    global $case;
    if ($destination !== $case[4] || $status !== 301) {
        fwrite(STDERR, "Unexpected redirect: $destination\n");
        exit(1);
    }
    echo "PASS {$case[0]} -> $destination\n";
}
$source = file_get_contents(__DIR__ . '/../inc/language-manager.php');
$start = strpos($source, 'function zk_redirect_localized_trailing_slash()');
$end = strpos($source, "add_action( 'template_redirect', 'zk_redirect_localized_trailing_slash'", $start);
eval(substr($source, $start, $end - $start));
zk_redirect_localized_trailing_slash();
if ($case[4] !== null) { fwrite(STDERR, "Expected redirect missing\n"); exit(1); }
echo "PASS {$case[0]} unchanged\n";
