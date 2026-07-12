<?php
// ==========================================
// NUVIO (STREMIO) MOVIES SCRAPER ADDON
// ==========================================

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH);

    // Only intercept requests for /nuvio-movies-addon/
    if (strpos($path, '/nuvio-movies-addon/') === false) {
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
    header('Cache-Control: no-cache, must-revalidate, max-age=0');

    $log_file = __DIR__ . '/nuvio-movies-debug.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - MOVIE REQ - " . $uri . "\n", FILE_APPEND);

    // 2. Manifest Endpoint
    if (strpos($path, '/nuvio-movies-addon/manifest.json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'id' => 'org.zurabkostava.movies.scraper',
            'version' => '1.0.0',
            'name' => 'Nuvio Movies Pro',
            'description' => 'Ultra-fast HTTP direct stream scraper for Nuvio.',
            'types' => array('movie'),
            'resources' => array('stream'),
            'idPrefixes' => array('tt'),
            'catalogs' => array()
        ));
        exit;
    }

    // 3. Stream Endpoint
    if (preg_match('#/nuvio-movies-addon/stream/movie/([^/]+?)(?:/([^/]+?))?\.json$#', $path, $matches)) {
        header('Content-Type: application/json; charset=utf-8');
        
        $imdb_id = urldecode($matches[1]);
        $streams = array();
        
        // --- PROVIDER 1: PHP cURL Scraper (Attempting to bypass Cloudflare/JS) ---
        function zk_fetch_page($url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
                "Accept-Language: en-US,en;q=0.5",
                "Connection: keep-alive"
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $html = curl_exec($ch);
            curl_close($ch);
            return $html;
        }

        $direct_link = false;
        
        // Target A: autoembed.to
        $html = zk_fetch_page("https://autoembed.to/movie/imdb/" . $imdb_id);
        if (preg_match('/(https?:\/\/[^\s"\']+\.m3u8)/i', $html, $m)) {
            $direct_link = $m[1];
        } else {
            // Target B: vsembed.ru (vidsrc clone)
            $html2 = zk_fetch_page("https://vsembed.ru/embed/movie/" . $imdb_id);
            if (preg_match('/(https?:\/\/[^\s"\']+\.m3u8)/i', $html2, $m)) {
                $direct_link = $m[1];
            } else {
                // Try finding an iframe and scraping that
                if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html2, $iframe_match)) {
                    $iframe_url = $iframe_match[1];
                    if (strpos($iframe_url, '//') === 0) $iframe_url = 'https:' . $iframe_url;
                    
                    $iframe_html = zk_fetch_page($iframe_url);
                    if (preg_match('/(https?:\/\/[^\s"\']+\.m3u8)/i', $iframe_html, $m)) {
                        $direct_link = $m[1];
                    }
                }
            }
        }
        
        if ($direct_link) {
            $streams[] = array(
                'name' => 'Nuvio Pro',
                'title' => '1080p / Direct Stream',
                'url' => $direct_link
            );
        } else {
            // Fallback to External URL if scraping blocked by Cloudflare
            $streams[] = array(
                'name' => 'Nuvio Pro',
                'title' => 'Browser Fallback (CF Blocked)',
                'externalUrl' => 'https://autoembed.to/movie/imdb/' . $imdb_id
            );
        }
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - MOVIE SCRAPED - ID: $imdb_id | Success: " . ($direct_link ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
        echo json_encode(array('streams' => $streams));
        exit;
    }
});
