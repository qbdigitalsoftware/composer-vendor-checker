<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Service;

/**
 * Service to integrate with Composer and check installed packages
 */
class ComposerIntegration
{
    /** @var string */
    protected $composerLockPath;

    /** @var VersionChecker */
    protected $versionChecker;

    /** @var array Known package URL mappings */
    protected $packageUrlMappings = [
        // Amasty modules
        'amasty/module-admin-actions-log' => 'https://amasty.com/admin-actions-log-for-magento-2.html',
        'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
        'amasty/shopby' => 'https://amasty.com/improved-layered-navigation-for-magento-2.html',
        'amasty/geoip' => 'https://amasty.com/geoip-for-magento-2.html',
        
        // Mageplaza modules
        'mageplaza/module-layered-navigation-m2' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        'mageplaza/layered-navigation-m2-pro' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        'mageplaza/module-layered-navigation-m2-ultimate' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        
        // BSS Commerce modules
        'bsscommerce/module-customer-approval' => 'https://bsscommerce.com/magento-2-customer-approval-extension.html',
        
        // MageMe modules
        'mageme/module-webforms-3' => 'https://mageme.com/magento-2-form-builder.html',
        'mageme/module-webforms' => 'https://mageme.com/magento-2-form-builder.html',
        
        // Mageworx modules
        'mageworx/module-giftcards' => 'https://www.mageworx.com/magento-2-gift-cards.html',
        
        // XTENTO modules
        'xtento/orderexport' => 'https://www.xtento.com/magento-extensions/magento-order-export-module.html',
        
        // Add more mappings as needed
    ];

    /**
     * Constructor
     *
     * @param string $composerLockPath
     */
    public function __construct($composerLockPath = './composer.lock')
    {
        $this->composerLockPath = $composerLockPath;
        $this->versionChecker = new VersionChecker();
    }

    /**
     * Add a custom package URL mapping
     *
     * @param string $package
     * @param string $url
     */
    public function addPackageUrlMapping($package, $url)
    {
        $this->packageUrlMappings[$package] = $url;
    }

    /**
     * Get all package URL mappings
     *
     * @return array
     */
    public function getPackageUrlMappings()
    {
        return $this->packageUrlMappings;
    }

    /**
     * Get list of supported vendor names
     *
     * @return array
     */
    public function getSupportedVendors()
    {
        $supportedVendorDomains = $this->versionChecker->getSupportedVendors();
        
        // Extract vendor names from domains (e.g., 'amasty.com' -> 'amasty')
        return array_map(function($domain) {
            return explode('.', $domain)[0];
        }, $supportedVendorDomains);
    }

    /**
     * Get installed packages from composer.lock
     * Only returns packages from vendors that have defined patterns in VersionChecker
     *
     * @param array $vendorFilter Optional vendor names to filter (e.g., ['amasty', 'mageplaza'])
     * @return array
     */
    public function getInstalledPackages(array $vendorFilter = [])
    {
        if (!file_exists($this->composerLockPath)) {
            throw new \Exception("composer.lock not found at: {$this->composerLockPath}");
        }

        $lockData = json_decode(file_get_contents($this->composerLockPath), true);
        
        if (!isset($lockData['packages'])) {
            throw new \Exception("Invalid composer.lock format");
        }

        // Get supported vendors from VersionChecker (vendors with patterns)
        $supportedVendorDomains = $this->versionChecker->getSupportedVendors();
        
        // Extract vendor names from domains (e.g., 'amasty.com' -> 'amasty')
        $supportedVendors = array_map(function($domain) {
            return explode('.', $domain)[0];
        }, $supportedVendorDomains);

        $packages = [];
        
        foreach ($lockData['packages'] as $package) {
            $packageName = $package['name'];
            $vendor = $this->versionChecker->getVendorFromPackage($packageName);
            
            // Skip if vendor is not in our supported list
            if (!in_array($vendor, $supportedVendors)) {
                continue;
            }
            
            // Apply additional vendor filter if specified
            if (!empty($vendorFilter)) {
                if (!in_array($vendor, $vendorFilter)) {
                    continue;
                }
            }

            // Only include packages we have URL mappings for
            if (isset($this->packageUrlMappings[$packageName])) {
                $packages[$packageName] = [
                    'name' => $packageName,
                    'version' => $package['version'],
                    'url' => $this->packageUrlMappings[$packageName]
                ];
            }
        }

        return $packages;
    }

    /**
     * Check for updates for all installed packages
     *
     * @param bool $verbose
     * @return array
     */
    public function checkForUpdates($verbose = false)
    {
        $installedPackages = $this->getInstalledPackages();
        $results = [];

        foreach ($installedPackages as $packageName => $packageInfo) {
            try {
                $vendorData = $this->versionChecker->getVendorVersion($packageInfo['url']);
                
                $result = [
                    'package' => $packageName,
                    'installed_version' => $packageInfo['version'],
                    'latest_version' => $vendorData['latest_version'],
                    'vendor_url' => $packageInfo['url'],
                    'status' => $this->compareVersions($packageInfo['version'], $vendorData['latest_version']),
                    'checked_at' => $vendorData['checked_at']
                ];

                if ($verbose && !empty($vendorData['changelog'])) {
                    $result['recent_changes'] = array_slice($vendorData['changelog'], 0, 3);
                }

                $results[] = $result;

            } catch (\Exception $e) {
                $results[] = [
                    'package' => $packageName,
                    'installed_version' => $packageInfo['version'],
                    'latest_version' => 'Error',
                    'vendor_url' => $packageInfo['url'],
                    'status' => 'ERROR',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Compare two version strings
     *
     * @param string $installed
     * @param string $latest
     * @return string
     */
    protected function compareVersions($installed, $latest)
    {
        // Remove 'v' prefix if present
        $installed = ltrim($installed, 'v');
        $latest = ltrim($latest, 'v');

        if (version_compare($installed, $latest, '=')) {
            return 'UP_TO_DATE';
        } elseif (version_compare($installed, $latest, '<')) {
            return 'UPDATE_AVAILABLE';
        } else {
            return 'AHEAD_OF_VENDOR';
        }
    }

    /**
     * Generate a human-readable report
     *
     * @param array $results
     * @return string
     */
    public function generateReport(array $results)
    {
        $report = [];
        $report[] = "╔════════════════════════════════════════════════════════════════════════════╗";
        $report[] = "║                        Vendor Version Check Report                         ║";
        $report[] = "╚════════════════════════════════════════════════════════════════════════════╝";
        $report[] = "";

        $updateCount = 0;
        $upToDateCount = 0;
        $errorCount = 0;

        foreach ($results as $result) {
            $package = $result['package'];
            $installed = $result['installed_version'];
            $latest = $result['latest_version'];
            $status = $result['status'];

            // Status symbol
            $statusSymbol = '?';
            $statusColor = '';
            
            switch ($status) {
                case 'UP_TO_DATE':
                    $statusSymbol = '✓';
                    $upToDateCount++;
                    break;
                case 'UPDATE_AVAILABLE':
                    $statusSymbol = '↑';
                    $updateCount++;
                    break;
                case 'AHEAD_OF_VENDOR':
                    $statusSymbol = '⚠';
                    break;
                case 'ERROR':
                    $statusSymbol = '✗';
                    $errorCount++;
                    break;
            }

            $report[] = sprintf("  %s  %-50s", $statusSymbol, $package);
            $report[] = sprintf("      Installed: %-20s  Latest: %s", $installed, $latest);
            
            if (isset($result['recent_changes'])) {
                $report[] = "      Recent changes:";
                foreach ($result['recent_changes'] as $change) {
                    $report[] = sprintf("        • %s - %s", $change['version'], $change['date']);
                }
            }
            
            if (isset($result['error'])) {
                $report[] = "      Error: " . $result['error'];
            }
            
            $report[] = "";
        }

        $report[] = "─────────────────────────────────────────────────────────────────────────────";
        $report[] = sprintf("Summary: %d up-to-date, %d updates available, %d errors", 
            $upToDateCount, $updateCount, $errorCount);
        $report[] = "";

        return implode("\n", $report);
    }
}
