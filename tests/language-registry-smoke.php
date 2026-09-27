<?php
define( 'ABSPATH', __DIR__ );

function get_option( $key, $default = false ) {
    if ( 'zk_languages_v1' === $key ) {
        return array(
            'de' => array(
                'code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch',
                'locale' => 'de-DE', 'prefix' => 'de', 'enabled' => true,
            ),
        );
    }
    return $default;
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_title( $value ) { return sanitize_key( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }

require dirname( __DIR__ ) . '/inc/platform/languages.php';

$languages = zk_get_languages();
$checks = array(
    isset( $languages['en'], $languages['ka'], $languages['de'] ),
    'de' === zk_detect_language_from_path( '/de/about/' ),
    '/about/' === zk_strip_language_prefix( '/de/about/' ),
    '/ka/about/' === zk_get_language_path( '/de/about/', 'ka' ),
    '/about/' === zk_get_language_path( '/ka/about/', 'en' ),
    '_zk_content_de' === zk_language_meta_key( 'content', 'de' ),
);
if ( in_array( false, $checks, true ) ) {
    throw new RuntimeException( 'Language registry smoke test failed.' );
}

echo "Language registry smoke test passed.\n";
