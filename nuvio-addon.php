<?php
// ==========================================
// NUVIO (STREMIO) SUBTITLE ADDON REST API
// ==========================================

add_action('rest_api_init', function () {
    // 1. Manifest Endpoint
    register_rest_route('nuvio/v1', '/manifest.json', array(
        'methods' => 'GET',
        'callback' => 'nuvio_get_manifest',
        'permission_callback' => '__return_true'
    ));

    // 2. Subtitles Endpoint
    // Matches /nuvio/v1/subtitles/movie/tt1234567.json or /nuvio/v1/subtitles/series/tt1234567:1:1.json
    register_rest_route('nuvio/v1', '/subtitles/(?P<type>movie|series)/(?P<id>[^/]+)\.json', array(
        'methods' => 'GET',
        'callback' => 'nuvio_get_subtitles',
        'permission_callback' => '__return_true'
    ));

    // 3. SRT Stream Endpoint (with CORS headers for TV/Web)
    register_rest_route('nuvio/v1', '/stream/(?P<id>\d+)\.srt', array(
        'methods' => 'GET',
        'callback' => 'nuvio_stream_subtitle',
        'permission_callback' => '__return_true'
    ));
});

function nuvio_get_manifest() {
    $response = rest_ensure_response(array(
        'id' => 'org.zurabkostava.geosubtitles',
        'version' => '1.0.0',
        'name' => 'Nuvio GeoSubtitles',
        'description' => 'Georgian subtitles for movies and series automatically synced from the media library.',
        'types' => array('movie', 'series'),
        'resources' => array('subtitles'),
        'catalogs' => array(),
        'idPrefixes' => array('tt')
    ));
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
    return $response;
}

function nuvio_get_subtitles($request) {
    global $wpdb;
    
    $type = $request->get_param('type');
    $id = $request->get_param('id');
    
    if (!$id) {
        $response = rest_ensure_response(array('subtitles' => array()));
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
        return $response;
    }

    // Decode URL encoded characters if any
    $id = urldecode($id);
    
    // Split by colon to handle Stremio's extra parameters
    $parts = explode(':', $id);

    // Format search term based on type
    if ($type === 'series' && count($parts) >= 3) {
        // Only take ttID:season:episode
        $search_term = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
    } else {
        // Only take ttID
        $search_term = $parts[0];
    }

    $like = $wpdb->esc_like($search_term) . '%';
    
    // Search for SRT files starting with the required pattern in either post_title, post_name, or guid
    $sql = $wpdb->prepare("
        SELECT ID, guid 
        FROM {$wpdb->posts} 
        WHERE post_type='attachment' 
        AND guid LIKE %s 
        AND (post_title LIKE %s OR post_name LIKE %s OR guid LIKE %s)
    ", "%.srt", "%" . $like, "%" . $like, "%" . $like);
    
    $results = $wpdb->get_results($sql);
    
    $subtitles = array();
    
    foreach ($results as $file) {
        $srt_url = home_url('/wp-json/nuvio/v1/stream/' . $file->ID . '.srt');
        
        $subtitles[] = array(
            'id' => $id . '_ka',
            'url' => $srt_url,
            'lang' => 'ka'
        );
        $subtitles[] = array(
            'id' => $id . '_kat',
            'url' => $srt_url,
            'lang' => 'kat'
        );
        $subtitles[] = array(
            'id' => $id . '_geo',
            'url' => $srt_url,
            'lang' => 'Georgian'
        );
    }
    
    // In Stremio protocol, empty array inside 'subtitles' means no subs found
    $response = rest_ensure_response(array('subtitles' => $subtitles));
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
    return $response;
}

function nuvio_stream_subtitle($request) {
    $attachment_id = intval($request->get_param('id'));
    $file_path = get_attached_file($attachment_id);
    
    if (!$file_path || !file_exists($file_path)) {
        return new WP_Error('not_found', 'Subtitle not found', array('status' => 404));
    }
    
    $content = file_get_contents($file_path);
    
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
    header('Content-Type: application/x-subrip; charset=utf-8');
    echo $content;
    exit;
}
