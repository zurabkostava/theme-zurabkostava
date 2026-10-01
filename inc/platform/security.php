<?php
/**
 * Shared request-boundary helpers for public theme endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function zk_get_request_ip() {
    return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
}

function zk_request_within_rate_limit( $bucket, $limit, $window ) {
    $key   = 'zk_rl_' . md5( $bucket . '|' . zk_get_request_ip() );
    $state = get_transient( $key );

    if ( ! is_array( $state ) || ! isset( $state['count'] ) ) {
        set_transient( $key, array( 'count' => 1 ), $window );
        return true;
    }

    if ( (int) $state['count'] >= $limit ) {
        return false;
    }

    $state['count'] = (int) $state['count'] + 1;
    set_transient( $key, $state, $window );
    return true;
}

function zk_is_allowed_og_source_url( $url ) {
    if ( ! wp_http_validate_url( $url ) ) {
        return false;
    }

    $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
    $host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    if ( 'https' !== $scheme || empty( $host ) ) {
        return false;
    }

    $allowed_hosts = apply_filters( 'zk_allowed_og_source_hosts', array(
        'goodreads.com',
        'imdb.com',
        'spotify.com',
        'wikipedia.org',
        'youtube.com',
        'youtu.be',
    ) );

    foreach ( $allowed_hosts as $allowed_host ) {
        $allowed_host = strtolower( ltrim( (string) $allowed_host, '.' ) );
        $suffix       = '.' . $allowed_host;
        if ( $host === $allowed_host || ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Send a conservative security baseline for every public theme response.
 *
 * The CSP deliberately permits HTTPS-hosted assets because this site uses
 * Google Fonts, Supabase, media CDNs and embedded services. The restrictive
 * directives still prevent framing by other sites, plug-in content and an
 * unexpected document base URL without breaking those existing features.
 */
function zk_send_public_security_headers() {
    if ( is_admin() || headers_sent() ) {
        return;
    }

    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );

    $policy = array(
        "default-src 'self' https: data: blob:",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self' https:",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:",
        "style-src 'self' 'unsafe-inline' https:",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https:",
        "media-src 'self' data: blob: https:",
        "connect-src 'self' https: wss:",
        "frame-src 'self' https:",
        "worker-src 'self' blob:",
    );

    if ( is_ssl() ) {
        $policy[] = 'upgrade-insecure-requests';
    }

    header( 'Content-Security-Policy: ' . implode( '; ', $policy ) );
}
add_action( 'send_headers', 'zk_send_public_security_headers', 20 );
