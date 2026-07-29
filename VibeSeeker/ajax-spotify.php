<?php
// VibeSeeker - Spotify API Backend Proxy

add_action('rest_api_init', function () {
    register_rest_route('zk/v1', '/spotify-token', array(
        'methods' => 'GET',
        'callback' => 'zk_get_spotify_token_endpoint',
        'permission_callback' => '__return_true'
    ));
});

function zk_get_spotify_token_endpoint() {
    $token = get_transient('zk_spotify_token');
    if ($token) {
        return rest_ensure_response(array('access_token' => $token));
    }

    $client_id = get_option('zk_spotify_client_id');
    $client_secret = get_option('zk_spotify_client_secret');

    if (!$client_id || !$client_secret) {
        return new WP_Error('no_credentials', 'Spotify credentials not set in WP Options', array('status' => 500));
    }

    $response = wp_remote_post('https://accounts.spotify.com/api/token', array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => 'grant_type=client_credentials'
    ));

    if (is_wp_error($response)) {
        return new WP_Error('spotify_api_error', $response->get_error_message(), array('status' => 500));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['access_token'])) {
        // Cache token for 3500 seconds (expires in 3600)
        set_transient('zk_spotify_token', $body['access_token'], 3500);
        return rest_ensure_response(array('access_token' => $body['access_token']));
    }

    return new WP_Error('spotify_api_error', 'Invalid response from Spotify', array('status' => 500));
}
