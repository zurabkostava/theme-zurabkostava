<?php
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 404 );
    exit;
}

require_once '../../../../wp-load.php';
global $wp_rewrite;
print_r( array_slice( $wp_rewrite->wp_rewrite_rules(), 0, 5 ) );
