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
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Log every request (full URI incl. extra args) to see exactly what each client asks for
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/nuvio-log.txt';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - REQ - " . $uri . " - UA: " . $ua . "\n", FILE_APPEND);

    // 2. Manifest Endpoint
    if (strpos($path, '/nuvio-addon/manifest.json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'id' => 'org.zurabkostava.geosubtitles.raw',
            'version' => '3.3.0', // Bump to force client to get v6 URLs
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
        if (file_exists($log_file)) {
            echo "=== LAST 50 LINES OF LOG ===\n";
            $lines = file($log_file);
            echo implode('', array_slice($lines, -50));
            echo "\n============================\n\n";
        } else {
            echo "Log file not found at: $log_file\n\n";
        }
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
            // Remove the .srt extension from the URL. Nuvio blocks URLs ending in .srt!
            $stream_url = str_replace('http://', 'https://', home_url('/nuvio-addon/stream/v6/' . $file->ID));

            // Exactly ONE single entry for Georgian: 'ka'
            // Mimicking OpenSubtitles payload format exactly
            $subtitles[] = array(
                'id'               => $id . '_ka',
                'url'              => $stream_url,
                'lang'             => 'ka',
                'subtitleFileName' => 'Georgian.srt'
            );
        }

        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // 4. Stream Endpoint — serves .srt raw, or converts to .vtt on the fly
    // Regex now matches extensionless URLs for v6
    if (preg_match('#/nuvio-addon/stream/(?:v\d+/)?(\d+)(?:\.(srt|vtt))?$#', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        $format = isset($matches[2]) ? $matches[2] : 'srt'; // Default to srt if no extension
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            header('HTTP/1.1 404 Not Found');
            echo "Subtitle file not found.";
            exit;
        }

        $data = file_get_contents($file_path);

        // Normalize encoding (Strip BOM, fix Windows-1251)
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        } elseif (substr($data, 0, 2) === "\xFF\xFE") {
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE');
        } elseif (substr($data, 0, 2) === "\xFE\xFF") {
            $data = mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE');
        } elseif (!mb_detect_encoding($data, 'UTF-8', true)) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1251');
        }

        if ($format === 'vtt') {
            $data = str_replace(array("\r\n", "\r"), "\n", $data);
            $blocks = preg_split('/\n{2,}/', trim($data));
            $out = array();
            foreach ($blocks as $block) {
                $lines = explode("\n", $block);
                if (isset($lines[0]) && preg_match('/^\d+$/', trim($lines[0]))) {
                    array_shift($lines);
                }
                if (empty($lines)) { continue; }
                $timing = trim($lines[0]);
                if (preg_match('/^(?:(\d{1,2}):)?(\d{2}:\d{2})[,.](\d{2,3})\s*-->\s*(?:(\d{1,2}):)?(\d{2}:\d{2})[,.](\d{2,3})/', $timing, $t)) {
                    array_shift($lines);
                    $text = trim(implode("\n", $lines));
                    if ($text !== '') {
                        $startH = !empty($t[1]) ? str_pad($t[1], 2, '0', STR_PAD_LEFT) : '00';
                        $startRest = $t[2];
                        $startMs = str_pad($t[3], 3, '0', STR_PAD_RIGHT);
                        $endH = !empty($t[4]) ? str_pad($t[4], 2, '0', STR_PAD_LEFT) : '00';
                        $endRest = $t[5];
                        $endMs = str_pad($t[6], 3, '0', STR_PAD_RIGHT);
                        $out[] = "{$startH}:{$startRest}.{$startMs} --> {$endH}:{$endRest}.{$endMs}\n" . $text;
                    }
                } elseif (strpos($block, '-->') !== false) {
                    continue;
                } else {
                    $text = trim($block);
                    if ($text !== '' && !empty($out)) {
                        $out[count($out) - 1] .= "\n" . $text;
                    }
                }
            }
            $data = "WEBVTT\n\n" . implode("\n\n", $out) . "\n";
            header('Content-Type: text/vtt; charset=utf-8');
            header('Content-Disposition: inline; filename="Georgian.vtt"');
        } else {
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Content-Disposition: inline; filename="Georgian.srt"');
        }

        header('Accept-Ranges: bytes');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }
});
