<?php
$url = "https://api.github.com/repos/wujunwei928/edge-tts-go/releases/latest";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, "PHP Script");
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!$data || !isset($data['assets'])) {
    die("Failed to fetch releases.");
}

$linuxUrl = null;
foreach ($data['assets'] as $asset) {
    if (strpos($asset['name'], 'linux-amd64.tar.gz') !== false) {
        $linuxUrl = $asset['browser_download_url'];
    }
    if (strpos($asset['name'], 'windows-amd64.tar.gz') !== false) {
        $winUrl = $asset['browser_download_url'];
    }
}

if ($linuxUrl) {
    file_put_contents("linux.tar.gz", file_get_contents($linuxUrl));
    shell_exec("tar -xzf linux.tar.gz");
    rename("edge-tts-go", "edge-tts-linux");
    unlink("linux.tar.gz");
    echo "Linux binary ready.\n";
}
if ($winUrl) {
    file_put_contents("win.tar.gz", file_get_contents($winUrl));
    shell_exec("tar -xzf win.tar.gz");
    rename("edge-tts-go.exe", "edge-tts.exe");
    unlink("win.tar.gz");
    echo "Windows binary ready.\n";
}
?>
