<?php
$ticks = (int)(microtime(true) * 10000000 + 116444736000000000);
$ticks = $ticks - ($ticks % 3000000000);
$strToHash = $ticks . "6A5AA1D4EAFF4E9FB37E23D68491D6F4";
$secMsGec = strtoupper(hash('sha256', $strToHash));

$host = 'speech.platform.bing.com';
$port = 443;
$path = "/consumer/speech/synthesize/readaloud/edge/v1?TrustedClientToken=6A5AA1D4EAFF4E9FB37E23D68491D6F4&Sec-MS-GEC={$secMsGec}&Sec-MS-GEC-Version=1-130.0.2849.68";
$key = base64_encode(random_bytes(16));

$context = stream_context_create([
    'ssl' => [
        'SNI_enabled' => true,
        'peer_name' => $host,
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);
$fp = stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

$header = "GET $path HTTP/1.1\r\n";
$header .= "Host: $host\r\n";
$header .= "Upgrade: websocket\r\n";
$header .= "Connection: Upgrade\r\n";
$header .= "Sec-WebSocket-Key: $key\r\n";
$header .= "Sec-WebSocket-Version: 13\r\n";
$header .= "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits\r\n";
$header .= "Pragma: no-cache\r\n";
$header .= "Cache-Control: no-cache\r\n";
$header .= "Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold\r\n";
$header .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Edg/114.0.1823.18\r\n";
$header .= "\r\n";

fwrite($fp, $header);
$response = '';
while (!feof($fp)) {
    $line = fgets($fp);
    $response .= $line;
    if ($line === "\r\n") break;
}
echo "Response with full headers:\n$response\n";
fclose($fp);
?>
