<?php
/**
 * Example: Programmatic Usage of Vendor Version Checker
 *
 * This demonstrates how to use the checker as a library with custom
 * configuration, caching, and output formatting.
 *
 * For most use cases, running `composer vendor:check` from the CLI is
 * sufficient. This file is for advanced programmatic usage.
 */

use GetJohn\VendorChecker\Output\OutputFormatter;
use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Service\ResultCache;

// Auto-discovery mode — works on any Composer project with zero config.
// All packages from composer.lock are checked via Packagist by default.
$integration = new ComposerIntegration('./composer.lock');

// With private repos — pass composer.json and auth.json paths
$integration = new ComposerIntegration(
    './composer.lock',
    './composer.json',
    './auth.json'
);

// With custom config — pass a config file path
// See config/packages.php.example for the full config format.
$integration = new ComposerIntegration(
    './composer.lock',
    './composer.json',
    './auth.json',
    __DIR__ . '/my-packages.php'
);

// With result caching — pass a ResultCache instance
$cache = new ResultCache('./.vendor-check-cache', 3600);
$integration = new ComposerIntegration(
    './composer.lock',
    './composer.json',
    './auth.json',
    null,   // use default config
    $cache
);

// Run the check (no progress reporter in programmatic mode)
$results = $integration->checkForUpdates();

// Or filter to specific packages
$results = $integration->checkForUpdates(null, ['stripe/stripe-payments', 'amasty/promo']);

// Format as a table
$formatter = new OutputFormatter();
echo $formatter->formatTable($results);

// Or format as JSON
echo $formatter->formatJson($results);

// Or write CSV to file
$csv = $formatter->formatCsv($results);
$formatter->writeToFile($csv, '/tmp/vendor-report.csv');

// Process results programmatically
foreach ($results as $result) {
    if ($result['status'] === 'UPDATE_AVAILABLE') {
        echo sprintf(
            "Update available: %s (%s -> %s) [via %s]\n",
            $result['package'],
            $result['installed_version'],
            $result['latest_version'],
            $result['source'] ?? 'unknown'
        );
    }
}
