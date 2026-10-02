<?php
// Standalone migration/routing regression: preserves existing editorial data.
define( 'ABSPATH', __DIR__ );
$hooks = $meta = $options = $errors = array();
$posts = array(
    7 => (object) array( 'ID' => 7, 'post_type' => 'zk_book', 'post_name' => 'beta', 'post_status' => 'publish', 'post_title' => 'BETA', 'post_content' => 'Original book synopsis', 'menu_order' => 0 ),
    8 => (object) array( 'ID' => 8, 'post_type' => 'page', 'post_name' => 'beta', 'post_status' => 'publish', 'post_title' => 'Beta', 'post_content' => '', 'menu_order' => 0 ),
);
$meta[7] = array( '_zk_seo_title' => array( 'Existing book SEO' ), '_zk_content_ka' => array( 'Existing translated synopsis' ) );
$meta[8] = array( '_wp_page_template' => array( 'book-engine/template-book-reader.php' ), '_zk_seo_title' => array( 'Old page SEO' ), '_zk_title_ka' => array( 'ბეტა' ), '_zk_geo_ai_summary_ka' => array( 'Translated summary' ) );
function add_action( $name, $fn, $priority = 10 ) { global $hooks; $hooks[$name][] = $fn; }
function add_filter( $name, $fn, $priority = 10, $args = 1 ) { add_action( $name, $fn, $priority ); }
function get_post( $id ) { global $posts; return is_object( $id ) ? $id : ( $posts[$id] ?? null ); }
function get_post_field( $field, $id ) { return get_post( $id )->$field; }
function get_posts( $args ) { global $posts; return array_values( array_filter( $posts, function( $post ) use ( $args ) { return $post->post_type === $args['post_type'] && in_array( $post->post_status, (array) $args['post_status'], true ) && ( ! isset( $args['name'] ) || $post->post_name === $args['name'] ); } ) ); }
function get_post_meta( $id, $key = '', $single = false ) { global $meta; return ! $key ? ( $meta[$id] ?? array() ) : ( $single ? ( $meta[$id][$key][0] ?? '' ) : ( $meta[$id][$key] ?? array() ) ); }
function update_post_meta( $id, $key, $value ) { global $meta; $meta[$id][$key] = array( $value ); }
function get_option( $key ) { global $options; return $options[$key] ?? false; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[$key] = $value; }
function get_page_by_path( $path ) { return in_array( $path, array( 'books/beta', 'books/reserved' ), true ) ? get_post( 8 ) : null; }
function get_page_template_slug( $id ) { return get_post_meta( $id, '_wp_page_template', true ); }
function current_user_can( $cap, $id = null ) { return true; }
function maybe_unserialize( $value ) { return $value; }
function has_post_thumbnail( $id ) { return false; }
function wp_get_nav_menus() { return array( (object) array( 'term_id' => 1 ) ); }
function wp_get_nav_menu_items( $id ) { return array( (object) array( 'object_id' => 8, 'url' => 'https://example.com/books/beta/', 'target' => '_blank', 'menu_order' => 3 ) ); }
function wp_update_post( $data, $error = false ) { global $posts; foreach ( $data as $key => $value ) $posts[$data['ID']]->$key = $value; return $data['ID']; }
function is_wp_error( $value ) { return false; }
function is_admin() { return false; }
function wp_parse_url( $value, $component ) { return parse_url( $value, $component ); }
function zk_strip_language_prefix( $path ) { return preg_replace( '#^/ka(?=/|$)#', '', $path ); }
function zk_get_current_language() { return 'en'; }
function zk_get_language_path( $path, $code ) { return 'ka' === $code ? '/ka' . zk_strip_language_prefix( $path ) : zk_strip_language_prefix( $path ); }
function home_url( $path ) { return 'https://example.com' . $path; }
function zk_normalize_internal_destination( $url ) { return $url; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_is_post_revision( $id ) { return false; }
function wp_is_post_autosave( $id ) { return false; }
function sanitize_text_field( $value ) { return trim( $value ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function sanitize_title( $value ) { return strtolower( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce; }
function zk_project_save_error( $error ) { global $errors; $errors[] = $error; }
function clean_post_cache( $id ) {}
$wpdb = new class { public $posts = 'posts'; public function update( $table, $values, $where ) { wp_update_post( array_merge( array( 'ID' => $where['ID'] ), $values ) ); } };
require __DIR__ . '/../inc/platform/books.php';
function verify( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit(1); } }
zk_migrate_beta_book_record();
verify( get_post_meta( 7, '_zk_seo_title', true ) === 'Existing book SEO', 'Book SEO must win over Page SEO' );
verify( get_post_meta( 7, '_zk_content_ka', true ) === 'Existing translated synopsis', 'Preserve translated synopsis' );
verify( get_post_meta( 7, '_zk_geo_ai_summary_ka', true ) === 'Translated summary', 'Copy missing translated GEO' );
verify( get_post(8)->post_status === 'draft', 'Retain original Page as recoverable draft' );
$backup = get_post_meta( 7, '_zk_book_migration_backup', true );
verify( $backup['page']['post_status'] === 'publish' && $backup['book_meta']['_zk_seo_title'][0] === 'Existing book SEO', 'Back up both records before migration' );
verify( get_post_meta( 7, '_zk_book_reader_slug', true ) === 'beta', 'Keep the chapter/progress storage key' );
verify( get_post_meta( 7, '_zk_book_in_menu', true ) === '1' && get_post(7)->menu_order === 3, 'Preserve dropdown visibility and order' );
verify( zk_book_url(7, 'ka') === 'https://example.com/ka/books/beta/', 'Localized canonical URL' );
$request = $hooks['request'][0];
foreach ( array( '/books/beta/', '/ka/books/beta/', '/blog/zk_book/beta/' ) as $path ) {
    $_SERVER['REQUEST_URI'] = $path;
    verify( $request(array('pagename' => 'stale-page')) === array('post_type' => 'zk_book', 'p' => 7), "Resolve $path as the Book" );
}
verify( $request(array('preview' => true)) === array('preview' => true), 'Do not hijack previews' );
$_SERVER['REQUEST_URI'] = '/about/';
verify( $request(array('pagename' => 'about')) === array('pagename' => 'about'), 'Unrelated pages stay intact' );
$_POST = array( 'zk_book_publishing_nonce' => 'valid', 'zk_book_destination' => 'reader', 'zk_book_path' => '/books/new-beta/', 'zk_book_reader_slug' => 'different-key', 'zk_book_in_menu' => '1' );
zk_save_book_destination(7);
verify( zk_book_reader_slug(7) === 'beta', 'Changing a route must never change chapter/progress storage' );
verify( in_array('/books/beta/', get_post_meta(7, '_zk_book_previous_paths', true), true), 'Reserve the old URL for redirects' );
zk_migrate_beta_book_record();
verify( get_post_meta(7, '_zk_book_path', true) === '/books/new-beta/', 'Migration reruns must not reset edited routes' );
verify( get_post_meta(7, '_zk_book_migration_backup', true) === $backup, 'Backup stays immutable' );
$_POST['zk_book_path'] = '/books/reserved/';
get_post(8)->post_status = 'publish';
zk_save_book_destination(7);
verify( get_post_meta(7, '_zk_book_path', true) === '/books/new-beta/' && count($errors) === 1, 'Reject routes owned by published Pages' );
$_POST['zk_book_path'] = '/books/new-beta/';
$_POST['zk_book_destination'] = 'external';
zk_save_book_destination(7);
verify( get_post_meta(7, '_zk_book_path', true) === '/books/new-beta/' && count($errors) === 2, 'Protect existing readers from accidental removal' );
echo "PASS: Book migration, backups, localization, routing and storage-key preservation\n";
