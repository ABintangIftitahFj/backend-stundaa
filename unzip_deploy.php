<?php
/**
 * Stundaa Fast Unzip Script - Stable Debug Version
 */

$expectedToken = '9baadbcc253f56963953d2615646f344';
$providedToken = $_GET['token'] ?? '';

if (empty($expectedToken) || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    die("Error: Forbidden.");
}

echo "<pre>";
echo "--- Environment ---\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Dir: " . getcwd() . "\n";

$parentDir = dirname(__DIR__);
$zipPath = $parentDir . '/deploy.zip';

echo "\n--- File Check ---\n";
echo "Target Zip Path: $zipPath\n";

if (file_exists($zipPath)) {
    echo "Status: FOUND\n";
    echo "Size: " . filesize($zipPath) . " bytes\n";
    echo "Readable: " . (is_readable($zipPath) ? "Yes" : "No") . "\n";
    
    echo "\n--- Attempting Unzip ---\n";
    $zip = new ZipArchive;
    $res = $zip->open($zipPath);
    if ($res === TRUE) {
        echo "Zip opened successfully. Files count: " . $zip->numFiles . "\n";
        $extract = $zip->extractTo($parentDir);
        if ($extract) {
            echo "SUCCESS: Files extracted to $parentDir\n";
            // list first 5 files
            for($i = 0; $i < min(5, $zip->numFiles); $i++) {
                echo " - " . $zip->getNameIndex($i) . "\n";
            }
        } else {
            echo "ERROR: Extraction failed.\n";
        }
        $zip->close();
    } else {
        echo "ERROR: Could not open ZIP. Code: $res\n";
    }
} else {
    echo "Status: NOT FOUND\n";
    echo "\nChecking current directory instead...\n";
    $zipPath = __DIR__ . '/deploy.zip';
    if (file_exists($zipPath)) {
        echo "Found in current dir. Size: " . filesize($zipPath) . "\n";
    } else {
        echo "Not found in current dir either.\n";
    }
}
echo "</pre>";
