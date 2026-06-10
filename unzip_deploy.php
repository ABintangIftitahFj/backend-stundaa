<?php
/**
 * Stundaa Fast Unzip Script
 * This script extracts a deployment ZIP and replaces public_html content.
 *
 * Requires ?token=<DEPLOY_TOKEN> query param matching DEPLOY_TOKEN env var or hardcoded fallback.
 * Self-deletes after successful extraction.
 */

// --- Auth ---
$expectedToken = getenv('DEPLOY_TOKEN');
$providedToken = $_GET['token'] ?? '';

if (empty($expectedToken) || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    die("Error: Forbidden.");
}

// --- Extract ---
$zipFile  = __DIR__ . '/deploy.zip';
$extractTo = __DIR__ . '/';

if (!file_exists($zipFile)) {
    http_response_code(404);
    die("Error: deploy.zip not found.");
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

// Cleanup ZIP
unlink($zipFile);

// Self-delete this script so it is not an attack surface after deploy
$self = __FILE__;
echo "Success: Deployment extracted and cleaned up.";

// Self-delete runs after output flushed
register_shutdown_function(function () use ($self) {
    if (file_exists($self)) {
        unlink($self);
    }
});
