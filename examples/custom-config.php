<?php
/**
 * Example: Custom Vendor URL Configuration
 * 
 * This file demonstrates how to extend the vendor checker with your own
 * custom package URL mappings and vendor patterns.
 * 
 * Usage:
 * 1. Copy this to your project's root directory
 * 2. Modify with your custom URLs
 * 3. Include it before running the checker
 */

use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Service\VersionChecker;

// Example: Adding custom package URLs
$integration = new ComposerIntegration('./composer.lock');

// Add Amasty modules
$integration->addPackageUrlMapping(
    'amasty/custom-module',
    'https://amasty.com/custom-module-for-magento-2.html'
);

// Add Mageplaza modules
$integration->addPackageUrlMapping(
    'mageplaza/custom-extension',
    'https://www.mageplaza.com/magento-2-custom-extension/'
);

// Add BSS Commerce modules
$integration->addPackageUrlMapping(
    'bsscommerce/custom-module',
    'https://bsscommerce.com/custom-module-for-magento-2.html'
);

// Example: Adding a completely new vendor with custom patterns
// This requires extending the VersionChecker class or modifying it directly

/**
 * Custom Vendor Pattern Example
 * 
 * If you need to add a new vendor that's not supported out of the box,
 * you'll need to add their patterns to VersionChecker.php in the
 * $vendorPatterns array.
 * 
 * Example structure:
 * 
 * 'newvendor.com' => [
 *     'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
 *     'changelog_pattern' => '/##\s*v?(\d+\.\d+\.\d+)\s*\(([^)]+)\)(.*?)(?=##|$)/s',
 *     'composer_pattern' => '/composer\s+require\s+([\w\-\/]+)/i'
 * ]
 */

// Example: Run the checker programmatically
$results = $integration->checkForUpdates(true);

// Process results
foreach ($results as $result) {
    if ($result['status'] === 'UPDATE_AVAILABLE') {
        echo sprintf(
            "Update available: %s (%s → %s)\n",
            $result['package'],
            $result['installed_version'],
            $result['latest_version']
        );
    }
}

// Generate a report
echo "\n";
echo $integration->generateReport($results);
