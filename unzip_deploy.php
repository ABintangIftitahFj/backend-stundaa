<?php
/**
 * Stundaa Fast Unzip Script
 * Reads DEPLOY_TOKEN from:
 *   1. getenv() - works on VPS/cloud
 *   2. ../.deploy_token file - works on shared hosting (place file ABOVE public_html)
 *   3. $_ENV superglobal
 */

// --- Read token ---
$expectedToken = '9baadbcc253f56963953d2615646f344';

$providedToken = $_GET['token'] ?? '';

if (empty($expectedToken) || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    die("Error: Forbidden.");
}

// --- Extract ---
$zipFile  = file_exists(__DIR__ . '/deploy.zip') 
    ? __DIR__ . '/deploy.zip' 
    : __DIR__ . '/../deploy.zip';

$extractTo = file_exists(__DIR__ . '/deploy.zip')
    ? __DIR__ . '/'
    : __DIR__ . '/../';

if (!file_exists($zipFile)) {
    http_response_code(404);
    die("Error: deploy.zip not found at $zipFile");
}

$zip = new ZipArchive;
$opened = $zip->open($zipFile);

if ($opened !== TRUE) {
    http_response_code(500);
    die("Error: Failed to open deploy.zip (code: $opened).");
}

$extracted = $zip->extractTo($extractTo);
$zip->close();

if (!$extracted) {
    http_response_code(500);
    die("Error: Extraction failed.");
}

// --- Clear Laravel Caches ---
$storagePath = __DIR__ . '/storage/framework';
$cacheDirs = ['views', 'cache', 'sessions'];

foreach ($cacheDirs as $dir) {
    $path = "$storagePath/$dir";
    if (is_dir($path)) {
        $files = glob("$path/*");
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                unlink($file);
            }
        }
    }
}

// --- Reset PHP OPcache ---
if (function_exists('opcache_reset')) {
    opcache_reset();
}
clearstatcache();

unlink($zipFile);

$self = __FILE__;
echo "Success: Deployment extracted and cleaned up.";

register_shutdown_function(function () use ($self) {
    if (file_exists($self)) {
        unlink($self);
    }
});
