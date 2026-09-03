<?php
/**
 * Nuvio Subtitle Addon (Rewritten from scratch v4)
 */

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);

    // Only intercept requests for /nuvio-addon/
    if (strpos($path, '/nuvio-addon/') === false) {
        return;
    }

    // CORS Headers (Preflight and GET)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Range');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Max-Age: 86400');
        header('HTTP/1.1 204 No Content');
        exit;
    }

    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Logging
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/nuvio-log.txt';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - REQ - " . $uri . " - UA: " . $ua . "\n", FILE_APPEND);

    // Endpoint 1: Manifest
    if (strpos($path, '/nuvio-addon/manifest.json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'id' => 'org.zurabkostava.geosubtitles.raw',
            'version' => '4.1.0', // Bump to force client to get v8 URLs
            'name' => 'Nuvio Geo Subs Pro',
            'description' => 'Ultra-fast raw bypass Georgian subtitles synced from media library.',
            'types' => array('movie', 'series'),
            'resources' => array('subtitles'),
            'idPrefixes' => array('tt', 'tmdb'),
            'catalogs' => array()
        ));
        exit;
    }

    // Endpoint 2: Subtitles List
    if (preg_match('#/nuvio-addon/subtitles/(movie|series)/([^/]+)(?:/([^/]+))?\.json$#', $path, $matches)) {
        header('Content-Type: application/json; charset=utf-8');
        $id = urldecode($matches[2]); // e.g. tt1375666 or tt10394800:1:2
        
        global $wpdb;
        $search_id = str_replace(':', '%', $id);
        $sql = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s AND guid LIKE '%.srt'", '%' . $wpdb->esc_like($search_id) . '%');
        $results = $wpdb->get_results($sql);
        $subtitles = array();

        foreach ($results as $file) {
            // Georgian Stream (VTT)
            $geo_url = str_replace('http://', 'https://', home_url('/nuvio-addon/stream/v8/' . $file->ID . '.vtt'));
            $subtitles[] = array(
                'id'               => $id . '_ka',
                'url'              => $geo_url,
                'lang'             => 'ka',
                'format'           => 'vtt',
                'subtitleFileName' => 'Georgian.vtt'
            );
        }

        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // Endpoint 3: Stream
    // Matches /nuvio-addon/stream/v8/11134.vtt
    if (preg_match('#/nuvio-addon/stream/(?:v\d+/)?(\d+)\.vtt$#', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            header('HTTP/1.1 404 Not Found');
            echo "Subtitle file not found.";
            exit;
        }

        header('Content-Type: text/vtt; charset=utf-8');
        header('Content-Disposition: inline; filename="Subtitle.vtt"');

        // Serve real Georgian VTT
        $data = file_get_contents($file_path);

        // Normalize encoding
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        } elseif (!mb_detect_encoding($data, 'UTF-8', true)) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1251');
        }

        // Convert SRT to VTT precisely
        $data = str_replace(array("\r\n", "\r"), "\n", $data);
        $blocks = preg_split('/\n{2,}/', trim($data));
        
        $vtt = "WEBVTT\n\n";
        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            if (isset($lines[0]) && preg_match('/^\d+$/', trim($lines[0]))) {
                array_shift($lines);
            }
            if (empty($lines)) continue;
            
            $timing = trim($lines[0]);
            // Extract components regardless of 00: or 00:00: hours
            if (preg_match('/^(?:(\d{1,2}):)?(\d{2}:\d{2})[,.](\d{2,3})\s*-->\s*(?:(\d{1,2}):)?(\d{2}:\d{2})[,.](\d{2,3})/', $timing, $t)) {
                $startH = !empty($t[1]) ? str_pad($t[1], 2, '0', STR_PAD_LEFT) : '00';
                $startRest = $t[2];
                $startMs = str_pad($t[3], 3, '0', STR_PAD_RIGHT);
                $endH = !empty($t[4]) ? str_pad($t[4], 2, '0', STR_PAD_LEFT) : '00';
                $endRest = $t[5];
                $endMs = str_pad($t[6], 3, '0', STR_PAD_RIGHT);
                
                $vtt_timing = "{$startH}:{$startRest}.{$startMs} --> {$endH}:{$endRest}.{$endMs}";
                array_shift($lines);
                $text = trim(implode("\n", $lines));
                if ($text !== '') {
                    $vtt .= $vtt_timing . "\n" . $text . "\n\n";
                }
            }
        }

        header('Content-Length: ' . strlen($vtt));
        echo $vtt;
        exit;
    }

    // Endpoint 4: Debug
    if (strpos($path, '/nuvio-addon/debug') !== false) {
        header('Content-Type: text/plain; charset=utf-8');
        if (file_exists($log_file)) {
            echo "=== LAST 50 LINES OF LOG ===\n";
            $lines = file($log_file);
            echo implode('', array_slice($lines, -50));
        }
        exit;
    }

});
