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
    // Matches /nuvio-movies-addon/stream/movie/tt1234567.json (or with extra params)
    if (preg_match('#/nuvio-movies-addon/stream/movie/([^/]+?)(?:/([^/]+?))?\.json$#', $path, $matches)) {
        header('Content-Type: application/json; charset=utf-8');
        
        $imdb_id = urldecode($matches[1]);
        $streams = array();
        
        // --- PROVIDER 1: Public JSON API (Consumet/FlixHQ wrapper example) ---
        // For production, we would use a reliable API or robust scraper here.
        // We will mock a direct API call to a hypothetical or known API that returns m3u8.
        
        // This is a placeholder for the actual scraping logic. 
        // In reality, PHP would cURL vidsrc.me, parse the HTML, find the iframe, cURL the iframe, and extract the m3u8.
        
        // For demonstration, let's try to use a generic approach or return a placeholder stream
        // so you can test if Nuvio receives it.
        
        $streams[] = array(
            'name' => 'Nuvio Pro',
            'title' => '1080p / AutoEmbed',
            // We return an externalUrl for now if direct m3u8 scraping fails, 
            // BUT our goal is to return 'url' => 'https://...m3u8'
            // 'url' => 'https://example.com/movie.m3u8'
            'externalUrl' => 'https://autoembed.to/movie/imdb/' . $imdb_id
        );
        
        // To implement true .m3u8 extraction in PHP, we need a dedicated class that parses the specific provider.
        // Example structure for future expansion:
        // $m3u8_url = NuvioScraper::scrape_vidsrc($imdb_id);
        // if ($m3u8_url) { $streams[] = array('name'=>'VidSrc','url'=>$m3u8_url); }
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - MOVIE FOUND - " . count($streams) . " streams for $imdb_id\n", FILE_APPEND);
        
        echo json_encode(array('streams' => $streams));
        exit;
    }
});
