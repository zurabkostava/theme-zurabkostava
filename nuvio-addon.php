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
            'version' => '2.1.5', // bumped so clients refetch after the routing fix
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

            // Primary: WebVTT — universally supported by TV players (ExoPlayer, Tizen, WebOS web engines)
            // ტელევიზორების უმეტესობას (და Web ვერსიას) სჭირდება VTT და კონკრეტული ენის კოდი. ვაგზავნით რამდენიმე ვარიანტს!
            $subtitles[] = array('id' => $id . '_ka',  'url' => $base_url . '.vtt', 'lang' => 'ka');
            $subtitles[] = array('id' => $id . '_kat', 'url' => $base_url . '.vtt', 'lang' => 'kat');
            
            // ზოგ პლეერს (მაგ. Desktop MPV) ურჩევნია SRT
            $subtitles[] = array('id' => $id . '_geo', 'url' => $base_url . '.srt', 'lang' => 'geo');
            
            // დამატებითი ვარიანტები ყოველი შემთხვევისთვის
            $subtitles[] = array('id' => $id . '_ge',  'url' => $base_url . '.vtt', 'lang' => 'ge');
        }

        echo json_encode(array('subtitles' => $subtitles));
        exit;
    }

    // 4. Stream Endpoint — serves .srt raw, or converts to .vtt on the fly
    if (preg_match('#/nuvio-addon/stream/(\d+)\.(srt|vtt)$#', $path, $matches)) {
        $attachment_id = intval($matches[1]);
        $format = $matches[2];
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
        } elseif (!mb_detect_encoding($data, 'UTF-8', true)) {
            // If it's not valid UTF-8, it's likely Georgian ANSI (Windows-1251)
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1251');
        }

        if ($format === 'vtt') {
            // SRT -> WebVTT, block by block. Some source files contain corrupted timing
            // lines (e.g. "1632:29,369 --> ...") which make strict VTT parsers abort at
            // that cue — so invalid blocks are dropped instead of passed through.
            $data = str_replace(array("\r\n", "\r"), "\n", $data);
            $blocks = preg_split('/\n{2,}/', trim($data));
            $out = array();
            foreach ($blocks as $block) {
                $lines = explode("\n", $block);
                // Optional numeric cue index on the first line
                if (isset($lines[0]) && preg_match('/^\d+$/', trim($lines[0]))) {
                    array_shift($lines);
                }
                if (empty($lines)) {
                    continue;
                }
                $timing = trim($lines[0]);
                // Extremely permissive regex: allows optional hours, comma/period ms, and captures hours, minutes, seconds separately
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
                    continue; // corrupted timing line — skip the whole cue
                } else {
                    // Plain text block: a blank line inside the previous cue's text
                    // (common in these files) — merge it back into that cue.
                    $text = trim($block);
                    if ($text !== '' && !empty($out)) {
                        $out[count($out) - 1] .= "\n" . $text;
                    }
                }
            }
            // Add X-TIMESTAMP-MAP to force ExoPlayer to sync VTT to the beginning of the HLS stream (MPEGTS:0).
            // Without this, HLS streams with a non-zero PTS will cause subtitles to disappear.
            $data = "WEBVTT\nX-TIMESTAMP-MAP=MPEGTS:0,LOCAL:00:00:00.000\n\n" . implode("\n\n", $out) . "\n";
            header('Content-Type: text/vtt; charset=utf-8');
        } else {
            header('Content-Type: application/x-subrip; charset=utf-8');
        }

        header('Content-Disposition: inline');
        header('Accept-Ranges: bytes');

        $total = strlen($data);

        // Honor Range requests at origin (ExoPlayer probes tracks with ranged GETs)
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=\s*(\d*)\s*-\s*(\d*)/i', $_SERVER['HTTP_RANGE'], $range)) {
            $start = $range[1] === '' ? '' : intval($range[1]);
            $end   = $range[2] === '' ? '' : intval($range[2]);

            if ($start === '' && $end === '') {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$total");
                exit;
            }

            if ($start === '') {
                // Suffix byte range: e.g. 'bytes=-500' means the last 500 bytes
                $start = max(0, $total - $end);
                $end = $total - 1;
            } else {
                if ($end === '' || $end >= $total) {
                    $end = $total - 1;
                }
            }

            if ($start > $end || $start >= $total) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$total");
                exit;
            }

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
});
