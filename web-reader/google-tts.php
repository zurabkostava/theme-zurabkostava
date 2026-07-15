<?php
// google-tts.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: audio/mpeg");
header("Cache-Control: no-cache, must-revalidate");

if (!isset($_GET['text']) || !isset($_GET['tl'])) {
    http_response_code(400);
    exit("Missing parameters");
}

$text = $_GET['text'];
$tl = $_GET['tl'];

// Google Translate TTS URL
// client=tw-ob is the standard trick to bypass token requirements for short queries
$url = "https://translate.google.com/translate_tts?ie=UTF-8&q=" . urlencode($text) . "&tl=" . urlencode($tl) . "&client=tw-ob";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$audio = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200 && $audio) {
    echo $audio;
} else {
    http_response_code(500);
    exit("Failed to fetch audio from Google TTS");
}
?>
