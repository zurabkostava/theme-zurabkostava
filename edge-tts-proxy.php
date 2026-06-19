<?php
// edge-tts-proxy.php

// Disable error output in audio stream to prevent corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

function tts_log($msg) {
    file_put_contents(__DIR__ . '/tts_log.txt', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

tts_log("TTS Request: " . $_SERVER['REQUEST_URI']);

header('Content-Type: audio/mpeg');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$text = $_GET['text'] ?? '';
if (empty(trim($text))) {
    tts_log("Error: Empty text");
    http_response_code(400);
    die();
}

$voice = $_GET['voice'] ?? 'Microsoft Libby Online (Natural) - English (United States)';
$rate = $_GET['rate'] ?? '+0%';
$pitch = $_GET['pitch'] ?? '+0Hz';

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ws_frame($payload) {
    $b1 = 0x80 | 0x01; // FIN + text
    $length = strlen($payload);
    if($length <= 125) {
        $header = pack('CC', $b1, $length | 0x80);
    } elseif($length > 125 && $length < 65536) {
        $header = pack('CCn', $b1, 126 | 0x80, $length);
    } else {
        $header = pack('CCNN', $b1, 127 | 0x80, 0, $length);
    }
    $mask = random_bytes(4);
    $header .= $mask;
    $masked = '';
    for($i = 0; $i < $length; $i++) {
        $masked .= $payload[$i] ^ $mask[$i % 4];
    }
    return $header . $masked;
}

$host = 'speech.platform.bing.com';
$port = 443;
$path = '/consumer/speech/synthesize/readaloud/edge/v1?TrustedClientToken=6A5AA1D4EAFF4E9FB37E23D68491D6F4';

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$fp = stream_socket_client("ssl://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
if (!$fp) {
    tts_log("Error: stream_socket_client failed - $errstr ($errno)");
    http_response_code(500);
    die();
}

$key = base64_encode(random_bytes(16));
$req = "GET $path HTTP/1.1\r\n" .
       "Host: $host\r\n" .
       "Upgrade: websocket\r\n" .
       "Connection: Upgrade\r\n" .
       "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0\r\n" .
       "Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold\r\n" .
       "Sec-WebSocket-Key: $key\r\n" .
       "Sec-WebSocket-Version: 13\r\n\r\n";

fwrite($fp, $req);

$response = '';
while(!feof($fp)) {
    $line = fgets($fp);
    $response .= $line;
    if ($line == "\r\n") break;
}

if (strpos($response, '101 Switching Protocols') === false) {
    tts_log("Error: WebSocket handshake failed. Response:\n$response");
    http_response_code(500);
    die();
}

tts_log("Handshake successful. Sending config and SSML...");

$date = gmdate('D, d M Y H:i:s') . ' GMT';

// Send config
$config = "X-Timestamp: $date\r\nContent-Type: application/json; charset=utf-8\r\nPath: speech.config\r\n\r\n{\"context\":{\"synthesis\":{\"audio\":{\"metadataoptions\":{\"sentenceBoundaryEnabled\":\"false\",\"wordBoundaryEnabled\":\"true\"},\"outputFormat\":\"audio-24khz-48kbitrate-mono-mp3\"}}}}";
fwrite($fp, ws_frame($config));

// Generate valid SSML Language tag from Voice Name (hacky but works for edge)
// Ex: "Microsoft Eka Online (Natural) - Georgian (Georgia)" -> "ka-GE"
// Actually, edge TTS ignores xml:lang if the voice name matches perfectly.
$ssml = "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='en-US'><voice name='$voice'><prosody rate='$rate' pitch='$pitch'>".htmlspecialchars($text)."</prosody></voice></speak>";

$reqId = generateUUID();
$payload = "X-RequestId: $reqId\r\nContent-Type: application/ssml+xml\r\nX-Timestamp: $date\r\nPath: ssml\r\n\r\n$ssml";
fwrite($fp, ws_frame($payload));

stream_set_timeout($fp, 5);

while(!feof($fp)) {
    $header = fread($fp, 2);
    if(strlen($header) < 2) break;
    $opcode = ord($header[0]) & 0x0F;
    $len = ord($header[1]) & 0x7F;
    if($len == 126) {
        $ext = fread($fp, 2);
        $len = unpack('n', $ext)[1];
    } elseif($len == 127) {
        // Technically we need 8 bytes but in PHP unpack 'J' or 'N' might be needed
        // For Edge TTS, chunks are rarely over 65k, but just in case:
        $ext = fread($fp, 8);
        // Using N to read the lower 32-bits (max 4GB chunk, safe enough)
        $len = unpack('N', substr($ext, 4))[1]; 
    }
    
    $payload = '';
    while(strlen($payload) < $len) {
        $chunk = fread($fp, $len - strlen($payload));
        if ($chunk === false || strlen($chunk) === 0) break 2;
        $payload .= $chunk;
    }
    
    if ($opcode == 1) { // text frame
        if (strpos($payload, 'Path: turn.end') !== false) {
            tts_log("Success: Reached turn.end");
            break;
        }
    } elseif ($opcode == 2) { // binary frame
        $pos = strpos($payload, "Path: audio\r\n");
        if ($pos !== false) {
            $headerEnd = strpos($payload, "\r\n\r\n", $pos);
            if ($headerEnd !== false) {
                echo substr($payload, $headerEnd + 4);
                flush();
            }
        }
    } elseif ($opcode == 8) { // close frame
        tts_log("Error: Server closed connection prematurely");
        break;
    }
}
tts_log("Finished stream.");
fclose($fp);
