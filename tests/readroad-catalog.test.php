<?php
// Media Library changes must refresh a cached ReadRoad catalog.
define('HOUR_IN_SECONDS', 3600);
$attachments = array();
$cache = array();
$url_reads = 0;
class WP_Query {
    public $posts;
    function __construct($args) { global $attachments; $this->posts = $attachments; }
    function have_posts() { return !empty($this->posts); }
}
class WP_REST_Response {
    public $data, $headers;
    function __construct($data, $status, $headers) { $this->data = $data; $this->headers = $headers; }
}
function wp_upload_dir() { return array('basedir' => __DIR__ . '/absent-library', 'baseurl' => 'https://example.com/uploads'); }
function wp_mkdir_p($path) {}
function get_transient($key) { global $cache; return $cache[$key] ?? false; }
function set_transient($key, $value, $expiry) { global $cache; $cache[$key] = $value; }
function get_attached_file($id) { return false; }
function wp_get_attachment_url($id) { global $url_reads; $url_reads++; return 'https://example.com/uploads/' . $id . '.epub'; }
function get_post_meta($id, $key, $single) { return ''; }
function get_option($key) { return false; }
function wp_json_encode($data) { return json_encode($data); }
function check($condition, $message) { if (!$condition) throw new Exception($message); }
$source = file_get_contents(__DIR__ . '/../functions.php');
check(preg_match('/function neural_get_books\(\) \{.*?\n\}/s', $source, $match), 'Catalog function found');
eval($match[0]);
$attachments[] = (object) array('ID' => 1, 'post_title' => 'First', 'post_modified_gmt' => '2026-10-03 00:00:00');
check(count(neural_get_books()->data) === 1, 'Initial catalog');
$url_reads = 0;
check(count(neural_get_books()->data) === 1 && $url_reads === 0, 'Unchanged catalog reuses metadata');
$attachments[] = (object) array('ID' => 2, 'post_title' => 'Hawking', 'post_modified_gmt' => '2026-10-03 00:01:00');
$response = neural_get_books();
check(count($response->data) === 2, 'New Media Library EPUB appears immediately');
check(strpos($response->headers['Cache-Control'], 'no-store') !== false, 'Catalog avoids HTTP caching');
$attachments[1]->post_title = 'Updated';
$attachments[1]->post_modified_gmt = '2026-10-03 00:02:00';
check(neural_get_books()->data[1]['title'] === 'Updated', 'Metadata edit refreshes catalog');
array_pop($attachments);
check(count(neural_get_books()->data) === 1, 'Deleted attachment disappears');
echo "ReadRoad catalog regression: PASS\n";
