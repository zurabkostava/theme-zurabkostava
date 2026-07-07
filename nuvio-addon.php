
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
    register_rest_route('nuvio/v1', '/subtitles/(?P<type>movie|series)/(?P<id>[a-zA-Z0-9:]+)\.json', array(
        'methods' => 'GET',
        'callback' => 'nuvio_get_subtitles',
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
    return $response;
}

function nuvio_get_subtitles($request) {
    global $wpdb;
    
    $type = $request->get_param('type');
    $id = $request->get_param('id');
    
    if (!$id) {
        $response = rest_ensure_response(array('subtitles' => array()));
        $response->header('Access-Control-Allow-Origin', '*');
        return $response;
    }

    // Format search term based on type
    if ($type === 'series') {
        // tt8690918:1:1 -> tt8690918-1-1
        $search_term = str_replace(':', '-', $id);
    } else {
        $search_term = $id;
    }

    $like = $wpdb->esc_like($search_term) . '%';
    
    // Search for SRT files starting with the required pattern in either post_title, post_name, or guid
    $sql = $wpdb->prepare("
        SELECT guid 
        FROM {$wpdb->posts} 
        WHERE post_type='attachment' 
        AND guid LIKE %s 
        AND (post_title LIKE %s OR post_name LIKE %s OR guid LIKE %s)
    ", "%.srt", "%" . $like, "%" . $like, "%" . $like);
    
    $results = $wpdb->get_results($sql);
    
    $subtitles = array();
    
    foreach ($results as $file) {
        $subtitles[] = array(
            'id' => $id,
            'url' => $file->guid,
            'lang' => 'Georgian (GEO)'
        );
    }
    
    // In Stremio protocol, empty array inside 'subtitles' means no subs found
    $response = rest_ensure_response(array('subtitles' => $subtitles));
    $response->header('Access-Control-Allow-Origin', '*');
    return $response;
}
