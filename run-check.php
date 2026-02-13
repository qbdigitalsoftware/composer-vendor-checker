#!/usr/bin/env php
<?php
/**
 * Standalone runner for vendor version checks during development.
 * Usage: php run-check.php <path-to-composer.lock> [--format=json|csv|table] [--output=file]
 */

require __DIR__ . '/vendor/autoload.php';

use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Output\OutputFormatter;
use GetJohn\VendorChecker\Output\ProgressReporter;
use GetJohn\VendorChecker\Service\ResultCache;

// Parse arguments
$lockPath = $argv[1] ?? null;
$format = 'table';
$outputFile = null;
$noCache = false;

for ($i = 2; $i < $argc; $i++) {
    if (strpos($argv[$i], '--format=') === 0) {
        $format = substr($argv[$i], 9);
    } elseif (strpos($argv[$i], '--output=') === 0) {
        $outputFile = substr($argv[$i], 9);
    } elseif ($argv[$i] === '--no-cache') {
        $noCache = true;
    }
}

if (!$lockPath || !file_exists($lockPath)) {
    fwrite(STDERR, "Usage: php run-check.php <path-to-composer.lock> [--format=json|csv|table] [--output=file] [--no-cache]\n");
    exit(2);
}

$lockDir = dirname(realpath($lockPath));
$composerJson = $lockDir . '/composer.json';
$authJson = $lockDir . '/auth.json';
$configPath = __DIR__ . '/config/packages.php';

// Set up cache
$cache = null;
if (!$noCache) {
    $cache = new ResultCache($lockDir . '/.vendor-check-cache', 3600);
}

$integration = new ComposerIntegration(
    $lockPath,
    file_exists($composerJson) ? $composerJson : null,
    file_exists($authJson) ? $authJson : null,
    $configPath,
    $cache
);

$packages = $integration->getInstalledPackages();
$total = count($packages);

// Progress to stderr so it doesn't pollute JSON/CSV output
fwrite(STDERR, "Checking $total packages from: $lockPath\n\n");

// Simple progress callback via STDERR
$checked = 0;
$progress = null;
if ($format === 'table' || $outputFile) {
    // Use a simple stderr progress since we don't have Symfony Output
    $progress = null; // ProgressReporter needs OutputInterface, skip for standalone
}

$results = $integration->checkForUpdates(null, []);

// Format
$formatter = new OutputFormatter();
switch ($format) {
    case 'json':
        $formatted = $formatter->formatJson($results);
        break;
    case 'csv':
        $formatted = $formatter->formatCsv($results);
        break;
    default:
        $formatted = $formatter->formatTable($results);
        break;
}

if ($outputFile) {
    $formatter->writeToFile($formatted, $outputFile);
    fwrite(STDERR, "Results written to: $outputFile\n");
} else {
    echo $formatted . "\n";
}

// Exit code
$hasErrors = false;
$hasUpdates = false;
foreach ($results as $r) {
    if ($r['status'] === 'ERROR' || $r['status'] === 'UNAVAILABLE') $hasErrors = true;
    if ($r['status'] === 'UPDATE_AVAILABLE') $hasUpdates = true;
}
exit($hasErrors ? 2 : ($hasUpdates ? 1 : 0));
