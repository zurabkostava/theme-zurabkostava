<?php
/**
 * Stable cache versions for first-party theme assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function zk_asset_version( $relative_path ) {
    $file = get_template_directory() . '/' . ltrim( $relative_path, '/\\' );
    return file_exists( $file ) ? (string) filemtime( $file ) : (string) wp_get_theme()->get( 'Version' );
}
