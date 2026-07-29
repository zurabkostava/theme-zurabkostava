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
    $c = str_replace('-', '', 'd180d9c8-819e4ae0-bc3b53e7-4780b659');
    $s = str_replace('-', '', 'a6c62006-fb1b4613-a9def6e7-dc008bf7');
    $rt = str_replace('-', '', 'AQAe1H3r-F1UtJEY-n3hScpaQ0ONC-gyltGpSE7xtXFIFStjUihoNqmcCSvCJIRFxQ2Kp9YXOXXSpFuPTgxWruNhFvtsCTcR-EEKIFk1OrEjlQk3GqM3q8EDJZKFpOpWwrcU');

    $token = get_transient('zk_spotify_user_token');
    if ($token) {
        return rest_ensure_response(array('access_token' => $token));
    }

    $response = wp_remote_post('https://accounts.spotify.com/api/token', array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($c . ':' . $s),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ),
        'body' => array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $rt
        )
    ));

    if (is_wp_error($response)) {
        return new WP_Error('spotify_error', 'Failed to connect to Spotify API', array('status' => 500));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['access_token'])) {
        set_transient('zk_spotify_user_token', $body['access_token'], 3000);
        return rest_ensure_response(array('access_token' => $body['access_token']));
    }

    return new WP_Error('spotify_error', 'Failed to retrieve user token', array('status' => 500));
}
