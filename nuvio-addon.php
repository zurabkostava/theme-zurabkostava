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
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Range');
        header('Access-Control-Max-Age: 86400');
        header('HTTP/1.1 204 No Content');
        exit;
    }

    // Set standard CORS for all GET responses
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Range');
    // TV players read these from cross-origin responses only if exposed
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');
    header('Cache-Control: no-cache, must-revalidate, max-age=0'); // Prevent TV caching during tests

    // Log every request (full URI incl. extra args) to see exactly what each client asks for
    file_put_contents(get_template_directory() . '/nuvio-log.txt', date('Y-m-d H:i:s') . " - REQ - " . $uri . " - UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n", FILE_APPEND);

    // 2. Manifest Endpoint
    if (strpos($path, '/nuvio-addon/manifest.json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'id' => 'org.zurabkostava.geosubtitles.raw',
            'version' => '2.1.7', // bumped so clients refetch after the routing fix
            'name' => 'Nuvio Geo Subs Pro',
            'description' => 'Ultra-fast raw bypass Georgian subtitles synced from media library.',
            'types' => array('movie', 'series'),
            'resources' => array('subtitles'),
            'idPrefixes' => array('tt', 'tmdb'),
            'catalogs' => array()
        ));
        exit;
    }

    // 2.5 Debug Endpoint
    if (strpos($path, '/nuvio-addon/debug') !== false) {
        header('Content-Type: text/plain; charset=utf-8');
        global $wpdb;
        $res = $wpdb->get_results("SELECT ID, guid, post_title FROM {$wpdb->posts} WHERE post_type='attachment' AND guid LIKE '%.srt' LIMIT 5");
        echo "Found " . count($res) . " SRT files.\n\n";
        foreach ($res as $file) {
            $file_path = get_attached_file($file->ID);
            echo "ID: {$file->ID}\n";
            echo "Title: {$file->post_title}\n";
            echo "GUID: {$file->guid}\n";
            echo "Path: {$file_path}\n";
            echo "File Exists: " . (file_exists($file_path) ? "YES" : "NO") . "\n";
            
            if (file_exists($file_path)) {
                $data = file_get_contents($file_path);
                echo "File Size: " . strlen($data) . " bytes\n";
                echo "First 100 chars: \n" . substr($data, 0, 100) . "\n";
            }
            echo "------------------------\n\n";
        }
        exit;
    }

    // 3. Subtitles Endpoint
    // Stremio/Nuvio clients (especially on TV) request with an optional "extra" path segment:
    //   /subtitles/movie/tt123.json
    //   /subtitles/movie/tt123/videoHash=abc123&videoSize=456.json
    //   /subtitles/movie/tt123/filename=Some.Movie.2024.mkv.json
    // The old regex only matched the first form, so TV requests fell through to a WP 404 page.
    if (preg_match('#/nuvio-addon/subtitles/(movie|series)/([^/]+?)(?:/([^/]+?))?\.json$#', $path, $matches)) {
        header('Content-Type: application/json; charset=utf-8');
        global $wpdb;

        $type = $matches[1];
        $id = urldecode($matches[2]);
        // $matches[3] = extra args (videoHash, filename...) — accepted and ignored

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
            $base_url = str_replace('http://', 'https://', home_url('/nuvio-addon/stream/' . $file->ID));
            $cache_buster = '?v=' . time(); // Force CDN and TV cache bypass

            // ვაბრუნებთ მხოლოდ SRT-ს (Cache Buster-ით), რადგან 1 კვირის წინ მუშაობდა უპრობლემოდ!
            $subtitles[] = array('id' => $id . '_ka',  'url' => $base_url . '.srt' . $cache_buster, 'lang' => 'ka');
            $subtitles[] = array('id' => $id . '_geo', 'url' => $base_url . '.srt' . $cache_buster, 'lang' => 'geo');
        }

        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // 4. Stream Endpoint
    if (preg_match('/\/nuvio-addon\/stream\/(\d+)\.srt/', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        $file_path = get_attached_file($attachment_id);
        
        if ($file_path && file_exists($file_path)) {
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Accept-Ranges: bytes'); // აუცილებელია ზოგიერთი Smart TV პლეერისთვის (მაგ. ExoPlayer)
            header('Content-Length: ' . filesize($file_path)); // Crucial for ExoPlayer TV!
            readfile($file_path);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo "Subtitle file not found.";
        }
        exit;
    }
});
