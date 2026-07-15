<?php
// google-neural.php - Google Neural TTS Workaround via batchexecute
header("Access-Control-Allow-Origin: *");
header("Content-Type: audio/mpeg");
header("Cache-Control: no-cache, must-revalidate");

if (!isset($_POST['text']) || !isset($_POST['lang'])) {
    http_response_code(400);
    exit("Missing text or lang parameter");
}

$text = $_POST['text'];
$lang = $_POST['lang'];

$payload = json_encode([$text, $lang, null, "null"]);
$req = json_encode([[[ "jQ1olc", $payload, null, "generic" ]]]);

$ch = curl_init("https://translate.google.com/_/TranslateWebserverUi/data/batchexecute?rpcids=jQ1olc&f.sid=-7511462002511475704&bl=boq_translate-webserver_20230214.07_p0&hl=en&soc-app=1&soc-platform=1&soc-device=1&_reqid=59955");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "f.req=" . urlencode($req));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
]);
$res = curl_exec($ch);
curl_close($ch);

$jsonStr = preg_replace('/^\)\]\}\'\s*\n/', '', $res);
$data = json_decode($jsonStr, true);

if ($data && isset($data[0][2])) {
    $innerData = json_decode($data[0][2], true);
    if ($innerData && isset($innerData[0])) {
        $base64 = $innerData[0];
        echo base64_decode($base64);
        exit;
    }
}

http_response_code(500);
echo "Failed to fetch Google Neural TTS. Response was: " . substr($res, 0, 200);
?>
