<?php
// ==========================================
// NUVIO (STREMIO) RAW SUBTITLE ADDON
// ==========================================

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH);
    
    // Only intercept requests for /nuvio-addon/
    if (strpos($path, '/nuvio-addon/') === false) {
        return;
    }

    // 1. Handle OPTIONS (Preflight) instantly
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: *');
        header('HTTP/1.1 204 No Content');
        exit;
    }

    // Set standard CORS for all GET responses
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');

    // 2. Manifest Endpoint
    if (strpos($path, '/nuvio-addon/manifest.json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'id' => 'org.zurabkostava.geosubtitles.raw', // Changed ID to force new addon instance
            'version' => '2.0.0',
            'name' => 'Nuvio Geo Subs Pro',
            'description' => 'Ultra-fast raw bypass Georgian subtitles synced from media library.',
            'types' => array('movie', 'series'),
            'resources' => array(
                array(
                    'name' => 'subtitles',
                    'types' => array('movie', 'series')
                )
            ),
            'catalogs' => array()
        ));
        exit;
    }

    // 3. Subtitles Endpoint
    if (preg_match('/\/nuvio-addon\/subtitles\/(movie|series)\/([^\/]+)\.json/', $path, $matches)) {
        header('Content-Type: application/json; charset=utf-8');
        global $wpdb;
        
        $type = $matches[1];
        $id = urldecode($matches[2]);
        
        file_put_contents(get_template_directory() . '/nuvio-log.txt', date('Y-m-d H:i:s') . " - RAW SUBTITLES - $type - $id - " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown UA') . "\n", FILE_APPEND);

        $parts = explode(':', $id);
        if ($type === 'series' && count($parts) >= 3) {
            $search_term = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        } else {
            $search_term = $parts[0];
        }

        $like = $wpdb->esc_like($search_term) . '%';
        
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
            // Use home_url dynamically to ensure exact domain match, force https
            $srt_url = str_replace('http://', 'https://', home_url('/nuvio-addon/stream/' . $file->ID . '.srt'));
            
            $subtitles[] = array('id' => $id . '_ka', 'url' => $srt_url, 'lang' => 'ka');
            $subtitles[] = array('id' => $id . '_kat', 'url' => $srt_url, 'lang' => 'kat');
            $subtitles[] = array('id' => $id . '_geo', 'url' => $srt_url, 'lang' => 'geo');
            $subtitles[] = array('id' => $id . '_ge', 'url' => $srt_url, 'lang' => 'ge');
            $subtitles[] = array('id' => $id . '_kar', 'url' => $srt_url, 'lang' => 'kar');
            
            // Adding English just for testing. If this shows up, we know the TV was blocking Georgian codes.
            $subtitles[] = array('id' => $id . '_eng', 'url' => $srt_url, 'lang' => 'eng');
        }
        
        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // 4. Stream Endpoint
    if (preg_match('/\/nuvio-addon\/stream\/(\d+)\.srt/', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        $file_path = get_attached_file($attachment_id);
        
        file_put_contents(get_template_directory() . '/nuvio-log.txt', date('Y-m-d H:i:s') . " - RAW STREAM - $attachment_id - " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown UA') . "\n", FILE_APPEND);

        if ($file_path && file_exists($file_path)) {
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Content-Length: ' . filesize($file_path)); // Crucial for ExoPlayer TV!
            readfile($file_path);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo "Subtitle file not found.";
        }
        exit;
    }
});
