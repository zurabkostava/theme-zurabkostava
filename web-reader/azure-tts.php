<?php
// azure-tts.php - Workaround proxy for Edge TTS Neural voices using Python
header("Access-Control-Allow-Origin: *");
header("Content-Type: audio/mpeg");
header("Cache-Control: no-cache, must-revalidate");

if (!isset($_POST['text']) || !isset($_POST['voice'])) {
    http_response_code(400);
    exit("Missing parameters");
}

$text = $_POST['text'];
$voice = $_POST['voice'];

// Generate a unique temporary file
$tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'azure_tts_' . md5(uniqid(rand(), true)) . '.mp3';

$os = strtoupper(substr(PHP_OS, 0, 3));
$isWindows = ($os === 'WIN');

$binaryName = $isWindows ? 'edge-tts.exe' : 'edge-tts-linux';
$binaryPath = __DIR__ . DIRECTORY_SEPARATOR . $binaryName;

if (!$isWindows && file_exists($binaryPath)) {
    // Ensure executable permissions on Linux
    chmod($binaryPath, 0755);
}

// Build the shell command carefully escaping arguments
$cmd = escapeshellarg($binaryPath) . " --text " . escapeshellarg($text) . " --voice " . escapeshellarg($voice) . " --write-media " . escapeshellarg($tempFile);

// Execute the command
$output = shell_exec($cmd . " 2>&1");

if (file_exists($tempFile) && filesize($tempFile) > 0) {
    // Stream the file back
    readfile($tempFile);
    // Cleanup
    unlink($tempFile);
} else {
    http_response_code(500);
    exit("Failed to generate Azure TTS audio. Make sure the standalone binary '$binaryName' is present and executable.\nOutput: " . $output);
}
?>
