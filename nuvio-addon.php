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
            'version' => '4.6.0', // bumped to update language code
            'name' => 'Nuvio Geo Subs Pro',
            'description' => 'Ultra-fast raw bypass Georgian subtitles synced from media library.',
            'types' => array('movie', 'series'),
            'resources' => array('subtitles'),
            'idPrefixes' => array('tt', 'tmdb'),
            'catalogs' => array()
        ));
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
            // Extensionless URL, serving raw SRT directly
            $base_url = str_replace('http://', 'https://', home_url('/nuvio-addon/stream/' . $file->ID));
            $subtitles[] = array('id' => $id . '_ka',  'url' => $base_url, 'lang' => 'ka');
        }

        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // 4. Stream Endpoint — extensionless URL, serves pure SRT
    if (preg_match('#/nuvio-addon/stream/(\d+)$#', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            header('HTTP/1.1 404 Not Found');
            echo "Subtitle file not found.";
            exit;
        }

        $data = file_get_contents($file_path);

        // Normalize encoding: strip UTF-8 BOM, convert UTF-16 if a BOM is present
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        } elseif (substr($data, 0, 2) === "\xFF\xFE") {
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE');
        } elseif (substr($data, 0, 2) === "\xFE\xFF") {
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE');
        }

        header('Content-Type: application/x-subrip; charset=utf-8');
        header('Content-Disposition: inline');
        header('Accept-Ranges: bytes');

        $total = strlen($data);

        // Honor Range requests at origin (ExoPlayer probes tracks with ranged GETs)
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $range)) {
            $start = ($range[1] !== '') ? intval($range[1]) : 0;
            $end   = ($range[2] !== '') ? intval($range[2]) : $total - 1;
            if ($start > $end || $start >= $total) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$total");
                exit;
            }
            $end = min($end, $total - 1);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$total");
            header('Content-Length: ' . ($end - $start + 1));
            echo substr($data, $start, $end - $start + 1);
        } else {
            header('Content-Length: ' . $total);
            echo $data;
        }
        exit;
    }
    // 5. Debug Endpoint
    if (strpos($path, '/nuvio-addon/debug') !== false) {
        $log_file = get_template_directory() . '/nuvio-log.txt';
        header('Content-Type: text/plain; charset=utf-8');
        if (file_exists($log_file)) {
            echo "=== LAST 50 LINES OF LOG ===\n";
            $lines = file($log_file);
            echo implode('', array_slice($lines, -50));
        } else {
            echo "No log file found.";
        }
        exit;
    }
});
