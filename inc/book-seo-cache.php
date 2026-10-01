<?php
/**
 * Non-blocking SEO content for the interactive book reader.
 *
 * The reader gets the complete book from Supabase in the browser. WordPress
 * keeps a complete, persistent HTML copy for crawlers and refreshes it via cron,
 * so a slow Supabase response can never delay the page itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function zk_book_seo_option_key( $book_slug, $language ) {
    return 'zk_book_seo_v7_' . md5( $book_slug . '_' . $language );
}

function zk_book_normalize_seo_headings( $html ) {
    $html = preg_replace( '/<h1(\s[^>]*)?>/i', '<h2$1>', (string) $html );
    return preg_replace( '/<\/h1>/i', '</h2>', $html );
}

function zk_book_build_seo_content( $chapters, $language ) {
    $html        = '';
    $is_georgian = 'ka' === $language;

    foreach ( (array) $chapters as $chapter ) {
        $title = $is_georgian
            ? ( $chapter['title'] ?? '' )
            : ( $chapter['title_en'] ?? ( $chapter['title'] ?? '' ) );
        $content = $is_georgian
            ? ( $chapter['content'] ?? '' )
            : ( $chapter['content_en'] ?? ( $chapter['content'] ?? '' ) );

        if ( '' !== trim( $title ) ) {
            $html .= '<h2>' . esc_html( $title ) . '</h2>';
        }

        if ( '' !== trim( $content ) ) {
            $html .= wp_kses_post( zk_book_normalize_seo_headings( $content ) );
        }
    }

    return $html;
}

function zk_refresh_book_seo_cache( $book_slug, $language ) {
    $book_slug = sanitize_title( $book_slug );
    $language  = 'ka' === $language ? 'ka' : 'en';
    $base_url  = 'https://cblxbanbssnflgyrzhah.supabase.co';
    $api_key   = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImNibHhiYW5ic3NuZmxneXJ6aGFoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjM2Mzk0NDYsImV4cCI6MjA3OTIxNTQ0Nn0.36w4C_Y8TsTJ2ifORlE5vQu-yMHYCCD-Ebetz8CpQ9A';
    $url       = $base_url . '/rest/v1/book_projects?slug=eq.' . rawurlencode( $book_slug ) . '&select=chapters';
    $response  = wp_remote_get(
        $url,
        array(
            'headers' => array(
                'apikey'        => $api_key,
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 10,
        )
    );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data[0]['chapters'] ) || ! is_array( $data[0]['chapters'] ) ) {
        return;
    }

    update_option(
        zk_book_seo_option_key( $book_slug, $language ),
        array(
            'html'       => zk_book_build_seo_content( $data[0]['chapters'], $language ),
            'updated_at' => time(),
        ),
        false
    );
}
add_action( 'zk_refresh_book_seo_cache', 'zk_refresh_book_seo_cache', 10, 2 );

function zk_get_book_seo_content( $book_slug, $language ) {
    $language   = 'ka' === $language ? 'ka' : 'en';
    $option_key = zk_book_seo_option_key( $book_slug, $language );
    $cached     = get_option( $option_key, array() );

    if ( ! empty( $cached['html'] ) ) {
        if ( empty( $cached['updated_at'] ) || ( time() - (int) $cached['updated_at'] ) > DAY_IN_SECONDS ) {
            zk_schedule_book_seo_refresh( $book_slug, $language );
        }
        return $cached['html'];
    }

    // Preserve the complete previous cache without making a network request
    // during this page view. The cron refresh will update it shortly afterwards.
    $legacy_key = 'zk_book_seo_v4_' . md5( $book_slug . '_' . $language );
    $legacy     = get_transient( $legacy_key );
    if ( is_string( $legacy ) && '' !== trim( $legacy ) ) {
        $legacy = zk_book_normalize_seo_headings( $legacy );
        update_option(
            $option_key,
            array(
                'html'       => $legacy,
                'updated_at' => 0,
            ),
            false
        );
        zk_schedule_book_seo_refresh( $book_slug, $language );
        return $legacy;
    }

    zk_schedule_book_seo_refresh( $book_slug, $language );
    return '';
}

function zk_schedule_book_seo_refresh( $book_slug, $language ) {
    $args = array( sanitize_title( $book_slug ), 'ka' === $language ? 'ka' : 'en' );
    if ( ! wp_next_scheduled( 'zk_refresh_book_seo_cache', $args ) ) {
        wp_schedule_single_event( time() + 5, 'zk_refresh_book_seo_cache', $args );
    }
}
