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

// --- Debug Info ---
echo "<pre>";
echo "Current Directory: " . getcwd() . "\n";
echo "Script Directory: " . __DIR__ . "\n";

// --- Extract ---
$zipFile  = file_exists(__DIR__ . '/deploy.zip') 
    ? __DIR__ . '/deploy.zip' 
    : __DIR__ . '/../deploy.zip';

$extractTo = file_exists(__DIR__ . '/deploy.zip')
    ? __DIR__ . '/'
    : __DIR__ . '/../';

$realExtractTo = realpath($extractTo);
echo "Zip File Path: $zipFile\n";
echo "Attempting to Extract To: $extractTo\n";
echo "Resolved Extract Path: $realExtractTo\n";

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

echo "Zip contains " . $zip->numFiles . " files.\n";

$extracted = $zip->extractTo($extractTo);

if (!$extracted) {
    $zip->close();
    http_response_code(500);
    die("Error: Extraction failed to $extractTo. Check permissions.");
}

// List first few files for verification
for($i = 0; $i < min(10, $zip->numFiles); $i++) {
    echo "Extracted: " . $zip->getNameIndex($i) . "\n";
}

$zip->close();

// --- Clear Laravel Caches ---
// ... (rest of the logic)

echo "Success: Deployment extracted.\n";
echo "Please verify files and then manually delete this script and deploy.zip for security.\n";
echo "</pre>";

// unlink($zipFile); // Disabled for debugging
// register_shutdown_function(...) // Disabled for debugging
