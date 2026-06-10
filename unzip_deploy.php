<?php
/**
 * Stundaa Fast Unzip Script
 * This script extracts a deployment ZIP and replaces public_html content.
 */

$zipFile = 'deploy.zip';
$extractTo = './'; // Since this script will be in public_html

if (!file_exists($zipFile)) {
    die("Error: $zipFile not found.");
}

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    // Extract everything
    $zip->extractTo($extractTo);
    $zip->close();
    
    // Cleanup
    unlink($zipFile);
    
    echo "Success: Deployment extracted and cleaned up.";
} else {
    echo "Error: Failed to open $zipFile.";
}
?>
