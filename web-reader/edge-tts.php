<?php
/**
 * edge-tts.php — High-Performance Edge-TTS WebSocket Proxy
 * Provides Microsoft Edge Natural Neural voices (Eka, Giorgi, etc.) with 0 external dependencies.
 * Includes smart disk caching for ultra-fast response times.
 */

// 1. CORS & Preflight
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (isset($_GET['diag'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== PHP DIAGNOSTICS ===\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    $token = edge_tts_generate_token();
    echo "Token: $token\n";
    $connId = bin2hex(random_bytes(16));
    $secVer = '1-143.0.3650.75';
    $trustedClientToken = "6A5AA1D4EAFF4E9FB37E23D68491D6F4";
    $path = "/consumer/speech/synthesize/readaloud/edge/v1?TrustedClientToken={$trustedClientToken}&ConnectionId={$connId}&Sec-MS-GEC={$token}&Sec-MS-GEC-Version={$secVer}";
    
    echo "Resolved IPs: " . implode(', ', (array)gethostbynamel('speech.platform.bing.com')) . "\n";
    $targetHost = trim($_GET['host'] ?? 'speech.platform.bing.com');
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => 'speech.platform.bing.com',
        ]
    ]);
    $fp = @stream_socket_client("ssl://{$targetHost}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        exit("Socket connection to {$targetHost} failed: $errstr ($errno)\n");
    }
    echo "Connected to {$targetHost}:443\n";
    
    $wsKey = base64_encode(random_bytes(16));
    $muid = strtoupper(bin2hex(random_bytes(16)));
    $handshake = "GET {$path} HTTP/1.1\r\n" .
                 "Host: speech.platform.bing.com\r\n" .
                 "Connection: Upgrade\r\n" .
                 "Upgrade: websocket\r\n" .
                 "Sec-WebSocket-Key: {$wsKey}\r\n" .
                 "Sec-WebSocket-Version: 13\r\n" .
                 "Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold\r\n" .
                 "Pragma: no-cache\r\n" .
                 "Cache-Control: no-cache\r\n" .
                 "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0\r\n" .
                 "Accept-Encoding: gzip, deflate, br, zstd\r\n" .
                 "Accept-Language: en-US,en;q=0.9\r\n" .
                 "Cookie: muid={$muid};\r\n\r\n";
    edge_tts_ws_write_all($fp, $handshake);
    
    $handshakeResp = '';
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) break;
        $handshakeResp .= $line;
        if ($line === "\r\n" || $line === "\n") break;
    }
    echo "Handshake Response:\n$handshakeResp\n";
    
    $dateStr = gmdate('D M d Y H:i:s') . ' GMT+0000 (Coordinated Universal Time)';
    $config = "X-Timestamp:{$dateStr}\r\n" .
              "Content-Type:application/json; charset=utf-8\r\n" .
              "Path:speech.config\r\n\r\n" .
              '{"context":{"synthesis":{"audio":{"metadataoptions":{"sentenceBoundaryEnabled":"false","wordBoundaryEnabled":"false"},"outputFormat":"audio-24khz-48kbitrate-mono-mp3"}}}}\r\n';
    echo "Sending config (" . strlen($config) . " bytes)...\n";
    edge_tts_ws_send_frame($fp, $config, 1);
    
    $ssml = "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='en-US'>" .
            "<voice name='ka-GE-EkaNeural'><prosody pitch='+0Hz' rate='+0%' volume='+0%'>გამარჯობა</prosody></voice></speak>";
    $requestId = bin2hex(random_bytes(16));
    $ssmlMsg = "X-RequestId:{$requestId}\r\n" .
               "Content-Type:application/ssml+xml\r\n" .
               "X-Timestamp:{$dateStr}Z\r\n" .
               "Path:ssml\r\n\r\n" .
               $ssml;
    echo "Sending SSML (" . strlen($ssmlMsg) . " bytes)...\n";
    edge_tts_ws_send_frame($fp, $ssmlMsg, 1);
    
    echo "Reading server frames...\n";
    for ($frameIdx = 0; $frameIdx < 10; $frameIdx++) {
        $h = edge_tts_ws_read_exact($fp, 2);
        if (!$h) { echo "EOF on frame $frameIdx\n"; break; }
        $b1 = ord($h[0]); $b2 = ord($h[1]);
        $opcode = $b1 & 0x0F;
        $payLen = $b2 & 0x7F;
        if ($payLen === 126) {
            $payLen = unpack('n', edge_tts_ws_read_exact($fp, 2))[1];
        } elseif ($payLen === 127) {
            $arr = unpack('Nhigh/Nlow', edge_tts_ws_read_exact($fp, 8));
            $payLen = ($arr['high'] << 32) | $arr['low'];
        }
        $payload = edge_tts_ws_read_exact($fp, $payLen);
        if ($opcode === 1) {
            echo "--- FRAME $frameIdx (TEXT, {$payLen}b) ---\n" . trim($payload) . "\n";
        } elseif ($opcode === 2) {
            $hdrLen = unpack('n', substr($payload, 0, 2))[1];
            $hdr = substr($payload, 2, $hdrLen);
            $dataLen = strlen($payload) - 2 - $hdrLen;
            echo "--- FRAME $frameIdx (BINARY/AUDIO, {$dataLen}b audio) ---\n" . strtok($hdr, "\r\n") . "\n";
        } elseif ($opcode === 8) {
            $code = (strlen($payload) >= 2) ? unpack('n', substr($payload, 0, 2))[1] : 0;
            $reason = (strlen($payload) > 2) ? substr($payload, 2) : '';
            echo "--- FRAME $frameIdx (CLOSE code=$code, reason=$reason) ---\n";
            break;
        }
    }
    fclose($fp);
    exit;
}

// 2. Input Parameters
$text = trim($_GET['text'] ?? $_POST['text'] ?? '');
if (!$text) {
    http_response_code(400);
    exit("Missing required parameter: text");
}

$voice = trim($_GET['voice'] ?? $_POST['voice'] ?? 'ka-GE-EkaNeural');
$rawRate = trim($_GET['rate'] ?? $_POST['rate'] ?? '+0%');
$pitch = trim($_GET['pitch'] ?? $_POST['pitch'] ?? '+0Hz');

// Format rate as percentage string for SSML (e.g. 1.25 -> +25%, 0.8 -> -20%)
if (is_numeric($rawRate)) {
    $numRate = (float)$rawRate;
    $pct = round(($numRate - 1.0) * 100);
    $rate = ($pct >= 0 ? '+' : '') . $pct . '%';
} else {
    $rate = $rawRate;
    if (strpos($rate, '%') === false) {
        $rate .= '%';
    }
    if (!in_array($rate[0], ['+', '-'])) {
        $rate = '+' . $rate;
    }
}

// 3. Smart Cache Layer
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$cacheKey = md5($voice . '_' . $rate . '_' . $pitch . '_' . $text);
$cacheFile = $cacheDir . '/edge_' . $cacheKey . '.mp3';

if (file_exists($cacheFile) && filesize($cacheFile) > 200) {
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=86400');
    header('X-Cache: HIT');
    readfile($cacheFile);
    exit;
}

// 4. WebSocket Client Implementation (RFC 6455)
function edge_tts_generate_token() {
    $trusted = "6A5AA1D4EAFF4E9FB37E23D68491D6F4";
    $unixTime = time() + 11644473600;
    $unixTime -= ($unixTime % 300);
    $ticks = $unixTime . "0000000";
    return strtoupper(hash('sha256', $ticks . $trusted));
}

function edge_tts_ws_write_all($fp, $data) {
    $total = strlen($data);
    $written = 0;
    while ($written < $total) {
        $n = fwrite($fp, substr($data, $written));
        if ($n === false || $n === 0) return false;
        $written += $n;
    }
    fflush($fp);
    return true;
}

function edge_tts_ws_send_frame($fp, $payload, $opcode = 1) {
    $bytes = array_values(unpack('C*', $payload));
    $len = count($bytes);
    $first = 0x80 | ($opcode & 0x0F);
    if ($len <= 125) {
        $header = pack('CC', $first, 0x80 | $len);
    } elseif ($len <= 65535) {
        $header = pack('CCn', $first, 0x80 | 126, $len);
    } else {
        $header = pack('CCNN', $first, 0x80 | 127, 0, $len);
    }
    $mask = random_bytes(4);
    $maskBytes = array_values(unpack('C*', $mask));
    $masked = '';
    for ($i = 0; $i < $len; $i++) {
        $masked .= chr($bytes[$i] ^ $maskBytes[$i % 4]);
    }
    return edge_tts_ws_write_all($fp, $header . $mask . $masked);
}

function edge_tts_ws_read_exact($fp, $n) {
    $data = '';
    $retries = 0;
    while (strlen($data) < $n) {
        $chunk = fread($fp, $n - strlen($data));
        if ($chunk === false || strlen($chunk) === 0) {
            if (feof($fp)) return null;
            $meta = stream_get_meta_data($fp);
            if (!empty($meta['timed_out'])) return null;
            $retries++;
            if ($retries > 5000) return null; // 5s max wait
            usleep(1000);
            continue;
        }
        $data .= $chunk;
    }
    return $data;
}

// Connect to Microsoft Speech WebSocket
$token = edge_tts_generate_token();
$connId = bin2hex(random_bytes(16));
$secVer = '1-143.0.3650.75';
$trustedClientToken = "6A5AA1D4EAFF4E9FB37E23D68491D6F4";
$path = "/consumer/speech/synthesize/readaloud/edge/v1?TrustedClientToken={$trustedClientToken}&ConnectionId={$connId}&Sec-MS-GEC={$token}&Sec-MS-GEC-Version={$secVer}";

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'SNI_enabled' => true,
        'peer_name' => 'speech.platform.bing.com',
    ]
]);

$fp = @stream_socket_client('ssl://speech.platform.bing.com:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
if (!$fp) {
    http_response_code(502);
    exit("Edge TTS Connection Failed: $errstr ($errno)");
}

stream_set_timeout($fp, 15);

// Handshake
$wsKey = base64_encode(random_bytes(16));
$muid = strtoupper(bin2hex(random_bytes(16)));
$handshake = "GET {$path} HTTP/1.1\r\n" .
             "Host: speech.platform.bing.com\r\n" .
             "Connection: Upgrade\r\n" .
             "Upgrade: websocket\r\n" .
             "Sec-WebSocket-Key: {$wsKey}\r\n" .
             "Sec-WebSocket-Version: 13\r\n" .
             "Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold\r\n" .
             "Pragma: no-cache\r\n" .
             "Cache-Control: no-cache\r\n" .
             "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0\r\n" .
             "Accept-Encoding: gzip, deflate, br, zstd\r\n" .
             "Accept-Language: en-US,en;q=0.9\r\n" .
             "Cookie: muid={$muid};\r\n\r\n";

edge_tts_ws_write_all($fp, $handshake);

$handshakeResp = '';
while (!feof($fp)) {
    $line = fgets($fp, 1024);
    if ($line === false) break;
    $handshakeResp .= $line;
    if ($line === "\r\n" || $line === "\n") break;
}

if (strpos($handshakeResp, '101') === false) {
    fclose($fp);
    http_response_code(502);
    exit("Edge TTS Handshake Rejected: " . strtok($handshakeResp, "\r\n"));
}

// 5. Send Configuration Message
$dateStr = gmdate('D M d Y H:i:s') . ' GMT+0000 (Coordinated Universal Time)';
$config = "X-Timestamp:{$dateStr}\r\n" .
          "Content-Type:application/json; charset=utf-8\r\n" .
          "Path:speech.config\r\n\r\n" .
          '{"context":{"synthesis":{"audio":{"metadataoptions":{"sentenceBoundaryEnabled":"false","wordBoundaryEnabled":"false"},"outputFormat":"audio-24khz-48kbitrate-mono-mp3"}}}}\r\n';
edge_tts_ws_send_frame($fp, $config, 1);

// 6. Send SSML Message
$escapedText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$ssml = "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='en-US'>" .
        "<voice name='{$voice}'><prosody pitch='{$pitch}' rate='{$rate}' volume='+0%'>{$escapedText}</prosody></voice></speak>";

$requestId = bin2hex(random_bytes(16));
$ssmlMsg = "X-RequestId:{$requestId}\r\n" .
           "Content-Type:application/ssml+xml\r\n" .
           "X-Timestamp:{$dateStr}Z\r\n" .
           "Path:ssml\r\n\r\n" .
           $ssml;
edge_tts_ws_send_frame($fp, $ssmlMsg, 1);

// 7. Receive Audio Frames
$audioData = '';
$debugLog = [];
$debugLog[] = "Sent SSML voice={$voice}, rate={$rate}";

while (!feof($fp)) {
    $h = edge_tts_ws_read_exact($fp, 2);
    if ($h === null || strlen($h) < 2) {
        $debugLog[] = "EOF or timeout reading 2-byte header";
        break;
    }
    
    $b1 = ord($h[0]);
    $b2 = ord($h[1]);
    $opcode = $b1 & 0x0F;
    $masked = ($b2 & 0x80) !== 0;
    $payLen = $b2 & 0x7F;

    if ($payLen === 126) {
        $ext = edge_tts_ws_read_exact($fp, 2);
        if ($ext === null) {
            $debugLog[] = "Timeout reading 16-bit length";
            break;
        }
        $payLen = unpack('n', $ext)[1];
    } elseif ($payLen === 127) {
        $ext = edge_tts_ws_read_exact($fp, 8);
        if ($ext === null) {
            $debugLog[] = "Timeout reading 64-bit length";
            break;
        }
        $arr = unpack('Nhigh/Nlow', $ext);
        $payLen = ($arr['high'] << 32) | $arr['low'];
    }

    $mask = '';
    if ($masked) {
        $mask = edge_tts_ws_read_exact($fp, 4);
        if ($mask === null) {
            $debugLog[] = "Timeout reading mask";
            break;
        }
    }

    $payload = edge_tts_ws_read_exact($fp, $payLen);
    if ($payload === null) {
        $debugLog[] = "Timeout reading payload of len {$payLen}";
        break;
    }

    if ($masked) {
        for ($i = 0; $i < strlen($payload); $i++) {
            $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        }
    }

    if ($opcode === 2) { // Binary Frame
        if (strlen($payload) >= 2) {
            $hdrLen = unpack('n', substr($payload, 0, 2))[1];
            $hdr = substr($payload, 2, $hdrLen);
            $data = substr($payload, 2 + $hdrLen);
            $debugLog[] = "Binary frame (hdrLen={$hdrLen}): " . substr($hdr, 0, 60);
            if (strpos($hdr, 'Path:audio') !== false) {
                $audioData .= $data;
            }
        } else {
            $debugLog[] = "Binary frame too short: " . strlen($payload);
        }
    } elseif ($opcode === 1) { // Text Frame
        $debugLog[] = "Text frame: " . trim($payload);
        if (strpos($payload, 'Path:turn.end') !== false) {
            break;
        }
    } elseif ($opcode === 8) { // Close Frame
        $closeCode = (strlen($payload) >= 2) ? unpack('n', substr($payload, 0, 2))[1] : 0;
        $closeReason = (strlen($payload) > 2) ? substr($payload, 2) : '';
        $debugLog[] = "Close frame: code={$closeCode}, reason={$closeReason}";
        break;
    } else {
        $debugLog[] = "Opcode {$opcode} received";
    }
}

fclose($fp);

// 8. Output and Cache
if (strlen($audioData) > 0) {
    @file_put_contents($cacheFile, $audioData);
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audioData));
    header('Cache-Control: public, max-age=86400');
    header('X-Cache: MISS');
    echo $audioData;
    exit;
} else {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    exit("No audio received from Edge TTS service. Log:\n" . implode("\n", $debugLog));
}
