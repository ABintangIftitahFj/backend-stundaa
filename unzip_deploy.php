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

// List files in current directory
echo "\nFiles in " . __DIR__ . ":\n";
print_r(scandir(__DIR__));

// List files in parent directory
echo "\nFiles in " . dirname(__DIR__) . ":\n";
print_r(scandir(dirname(__DIR__)));

// --- Extract ---
$zipFileInCurrent = __DIR__ . '/deploy.zip';
$zipFileInParent = dirname(__DIR__) . '/deploy.zip';

if (file_exists($zipFileInCurrent)) {
    $zipFile = $zipFileInCurrent;
    $extractTo = __DIR__ . '/../'; // Extract from public/ to root public_html
    echo "\nFound deploy.zip in CURRENT directory.\n";
} elseif (file_exists($zipFileInParent)) {
    $zipFile = $zipFileInParent;
    $extractTo = dirname(__DIR__) . '/'; // Extract in root public_html
    echo "\nFound deploy.zip in PARENT directory.\n";
} else {
    http_response_code(404);
    die("\nError: deploy.zip NOT FOUND in current or parent directory.");
}

echo "Final Zip File Path: $zipFile\n";
echo "File Size: " . filesize($zipFile) . " bytes\n";
echo "File Permissions: " . substr(sprintf('%o', fileperms($zipFile)), -4) . "\n";

$zip = new ZipArchive;
$opened = $zip->open($zipFile);

if ($opened !== TRUE) {
    echo "\nZipArchive Open Error Code: $opened\n";
    // Reference codes: 19 = Not a zip archive (or can't find it), 11 = Can't open
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
