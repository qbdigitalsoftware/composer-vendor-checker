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

    /** @var string|null Path to composer.json (for reading repository definitions) */
    protected $composerJsonPath;

    /** @var string|null Path to auth.json (for private repo credentials) */
    protected $authJsonPath;

    /** @var VersionChecker */
    protected $versionChecker;

    /** @var array Cached private repo config: ['package' => ['repo_url' => '...', 'auth' => [...]]] */
    protected $privateRepoMap = [];

    /** @var array Known package URL mappings for vendor website scraping */
    protected $packageUrlMappings = [
        // Amasty modules (Cloudflare-protected — will fall back to error reporting)
        'amasty/module-admin-actions-log' => 'https://amasty.com/admin-actions-log-for-magento-2.html',
        'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
        'amasty/shopby' => 'https://amasty.com/improved-layered-navigation-for-magento-2.html',
        'amasty/geoip' => 'https://amasty.com/geoip-for-magento-2.html',
        'amasty/gdpr-cookie' => 'https://amasty.com/gdpr-cookie-compliance-for-magento-2.html',
        'amasty/geoipredirect' => 'https://amasty.com/geoip-redirect-for-magento-2.html',
        'amasty/module-gdpr' => 'https://amasty.com/gdpr-for-magento-2.html',
        'amasty/number' => 'https://amasty.com/custom-order-number-for-magento-2.html',

        // Aheadworks modules (Cloudflare-protected)
        'aheadworks/module-blog' => 'https://aheadworks.com/magento-2-blog-extension',

        // Mageplaza modules
        'mageplaza/module-layered-navigation-m2' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        'mageplaza/layered-navigation-m2-pro' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        'mageplaza/module-layered-navigation-m2-ultimate' => 'https://www.mageplaza.com/magento-2-layered-navigation/',
        'mageplaza/module-smtp' => 'https://www.mageplaza.com/magento-2-smtp/',

        // BSS Commerce modules
        'bsscommerce/module-customer-approval' => 'https://bsscommerce.com/magento-2-customer-approval-extension.html',
        // Note: bsscommerce/disable-compare has no public product page — tracked via Packagist

        // MageMe modules
        'mageme/module-webforms-3' => 'https://mageme.com/magento-2-form-builder.html',
        'mageme/module-webforms' => 'https://mageme.com/magento-2-form-builder.html',

        // MageWorx modules
        // Note: mageworx/module-giftcards has no version info on product page — tracked via Packagist
        // Note: mageworx/module-donationsmeta has no public product page — tracked via Packagist

        // XTENTO modules
        'xtento/orderexport' => 'https://www.xtento.com/magento-extensions/magento-order-export-module.html',
    ];

    /** @var array Packages to check via Packagist API only (no vendor website scraping) */
    protected $packagistPackages = [
        'taxjar/module-taxjar',
        'webshopapps/module-matrixrate',
        'klaviyo/magento2-extension',
        'yotpo/magento2-module-yotpo-loyalty',
        'yotpo/module-review',
        'paradoxlabs/authnetcim',
        'paradoxlabs/tokenbase',
        'justuno.com/m2',
        'stripe/stripe-payments',
        'bsscommerce/disable-compare',
        'mageworx/module-donationsmeta',
        'mageworx/module-giftcards',
    ];

    /**
     * Constructor
     *
     * @param string $composerLockPath
     * @param string|null $composerJsonPath Path to composer.json for repo definitions
     * @param string|null $authJsonPath Path to auth.json for private repo credentials
     */
    public function __construct($composerLockPath = './composer.lock', $composerJsonPath = null, $authJsonPath = null)
    {
        $this->composerLockPath = $composerLockPath;
        $this->composerJsonPath = $composerJsonPath;
        $this->authJsonPath = $authJsonPath;
        $this->versionChecker = new VersionChecker();

        if ($composerJsonPath && $authJsonPath) {
            $this->buildPrivateRepoMap();
        }
    }

    /**
     * Build a map of packages to their private Composer repos + auth credentials
     * Reads composer.json for repo URLs and auth.json for credentials
     */
    protected function buildPrivateRepoMap()
    {
        if (!file_exists($this->composerJsonPath) || !file_exists($this->authJsonPath)) {
            return;
        }

        $composerJson = json_decode(file_get_contents($this->composerJsonPath), true);
        $authJson = json_decode(file_get_contents($this->authJsonPath), true);

        if (!$composerJson || !$authJson) {
            return;
        }

        $repos = $composerJson['repositories'] ?? [];
        $httpBasic = $authJson['http-basic'] ?? [];

        // Hosts to skip — Magento core, internal satis, and marketplace repos
        $skipHosts = [
            'repo.magento.com',
            'marketplace.magento.com',
        ];

        // Host patterns to skip — agency/client satis repos with custom modules
        $skipPatterns = [
            '/\.satis\./i',         // e.g. cisco.satis.getjohn.co.uk
            '/\.getjohn\./i',       // e.g. any getjohn internal repo
        ];

        // Build list of private Composer repos with their auth
        $privateRepos = [];
        foreach ($repos as $repo) {
            if (!is_array($repo)) {
                continue;
            }
            $type = $repo['type'] ?? '';
            $url = $repo['url'] ?? '';

            if (!in_array($type, ['composer', '']) || empty($url)) {
                continue;
            }

            // Match repo URL host against auth.json keys
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || in_array($host, $skipHosts)) {
                continue;
            }

            // Skip hosts matching internal patterns
            $skipThis = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    $skipThis = true;
                    break;
                }
            }
            if ($skipThis) {
                continue;
            }
            if (isset($httpBasic[$host]['username'], $httpBasic[$host]['password'])) {
                $privateRepos[] = [
                    'url' => $url,
                    'host' => $host,
                    'auth' => [
                        'username' => $httpBasic[$host]['username'],
                        'password' => $httpBasic[$host]['password'],
                    ]
                ];
            }
        }

        if (empty($privateRepos)) {
            return;
        }

        // Read lock file to see which packages came from which repo
        if (!file_exists($this->composerLockPath)) {
            return;
        }

        $lockData = json_decode(file_get_contents($this->composerLockPath), true);
        $packages = $lockData['packages'] ?? [];

        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? '';
            $distUrl = $pkg['dist']['url'] ?? '';
            $notificationUrl = $pkg['notification-url'] ?? '';

            // Match package to its private repo by dist URL or notification URL
            foreach ($privateRepos as $repo) {
                if (
                    (strpos($distUrl, $repo['host']) !== false) ||
                    (strpos($notificationUrl, $repo['host']) !== false)
                ) {
                    $this->privateRepoMap[$name] = [
                        'repo_url' => $repo['url'],
                        'auth' => $repo['auth'],
                    ];
                    break;
                }
            }
        }
    }

    /**
     * Get private repo configuration for a package
     *
     * @param string $packageName
     * @return array|null ['repo_url' => '...', 'auth' => ['username' => '...', 'password' => '...']]
     */
    public function getPrivateRepoConfig($packageName)
    {
        return $this->privateRepoMap[$packageName] ?? null;
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
        return $this->versionChecker->getSupportedVendors();
    }

    /**
     * Get installed packages from composer.lock
     * Returns packages that have URL mappings OR are in the Packagist-only list
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

        $supportedVendors = $this->versionChecker->getSupportedVendors();

        $packages = [];

        foreach ($lockData['packages'] as $package) {
            $packageName = $package['name'];
            $vendor = $this->versionChecker->getVendorFromPackage($packageName);

            // Apply vendor filter if specified
            if (!empty($vendorFilter) && !in_array($vendor, $vendorFilter)) {
                continue;
            }

            // Include if we have a URL mapping (vendor website check)
            if (isset($this->packageUrlMappings[$packageName]) && in_array($vendor, $supportedVendors)) {
                $packages[$packageName] = [
                    'name' => $packageName,
                    'version' => $package['version'],
                    'url' => $this->packageUrlMappings[$packageName],
                    'check_method' => 'website'
                ];
                continue;
            }

            // Include if it's a Packagist-only package
            if (in_array($packageName, $this->packagistPackages)) {
                $packages[$packageName] = [
                    'name' => $packageName,
                    'version' => $package['version'],
                    'url' => null,
                    'check_method' => 'packagist'
                ];
                continue;
            }

            // Include if we have private repo credentials for it
            if (isset($this->privateRepoMap[$packageName])) {
                $packages[$packageName] = [
                    'name' => $packageName,
                    'version' => $package['version'],
                    'url' => null,
                    'check_method' => 'private_repo'
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
            $checkMethod = $packageInfo['check_method'] ?? 'website';

            // Packagist-only packages — check directly via Packagist API
            if ($checkMethod === 'packagist') {
                $latestVersion = $this->versionChecker->getPackagistVersion($packageName);
                if ($latestVersion !== null) {
                    $results[] = [
                        'package' => $packageName,
                        'installed_version' => $packageInfo['version'],
                        'latest_version' => $latestVersion,
                        'vendor_url' => 'https://packagist.org/packages/' . $packageName,
                        'source' => 'packagist',
                        'status' => $this->compareVersions($packageInfo['version'], $latestVersion),
                        'checked_at' => date('Y-m-d H:i:s')
                    ];
                } else {
                    $results[] = [
                        'package' => $packageName,
                        'installed_version' => $packageInfo['version'],
                        'latest_version' => 'N/A',
                        'vendor_url' => null,
                        'source' => 'packagist',
                        'status' => 'UNAVAILABLE',
                        'error' => 'Package not found on Packagist',
                        'checked_at' => date('Y-m-d H:i:s')
                    ];
                }
                continue;
            }

            // Private Composer repo check
            if ($checkMethod === 'private_repo') {
                $repoConfig = $this->getPrivateRepoConfig($packageName);
                if ($repoConfig) {
                    $latestVersion = $this->versionChecker->getPrivateRepoVersion(
                        $packageName,
                        $repoConfig['repo_url'],
                        $repoConfig['auth']
                    );
                    if ($latestVersion !== null) {
                        $results[] = [
                            'package' => $packageName,
                            'installed_version' => $packageInfo['version'],
                            'latest_version' => $latestVersion,
                            'vendor_url' => $repoConfig['repo_url'],
                            'source' => 'private_repo',
                            'status' => $this->compareVersions($packageInfo['version'], $latestVersion),
                            'checked_at' => date('Y-m-d H:i:s')
                        ];
                    } else {
                        $results[] = [
                            'package' => $packageName,
                            'installed_version' => $packageInfo['version'],
                            'latest_version' => 'N/A',
                            'vendor_url' => $repoConfig['repo_url'],
                            'source' => 'private_repo',
                            'status' => 'UNAVAILABLE',
                            'error' => 'Could not resolve version from private repo (auth may be expired)',
                            'checked_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
                continue;
            }

            // Website check with Packagist fallback
            try {
                $vendorData = $this->versionChecker->getVendorVersion($packageInfo['url'], $packageName);

                $latestVersion = $vendorData['latest_version'];

                // Handle null version — page loaded but no version found
                if ($latestVersion === null) {
                    $results[] = [
                        'package' => $packageName,
                        'installed_version' => $packageInfo['version'],
                        'latest_version' => 'N/A',
                        'vendor_url' => $packageInfo['url'],
                        'source' => $vendorData['source'] ?? 'vendor_website',
                        'status' => 'UNAVAILABLE',
                        'error' => 'No version information found on vendor page or Packagist',
                        'checked_at' => $vendorData['checked_at']
                    ];
                    continue;
                }

                $result = [
                    'package' => $packageName,
                    'installed_version' => $packageInfo['version'],
                    'latest_version' => $latestVersion,
                    'vendor_url' => $packageInfo['url'],
                    'source' => $vendorData['source'] ?? 'vendor_website',
                    'status' => $this->compareVersions($packageInfo['version'], $latestVersion),
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
        $unavailableCount = 0;

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
                case 'UNAVAILABLE':
                    $statusSymbol = '?';
                    $unavailableCount++;
                    break;
                case 'ERROR':
                    $statusSymbol = '✗';
                    $errorCount++;
                    break;
            }

            $source = isset($result['source']) ? $result['source'] : '';
            $sourceLabels = [
                'packagist' => ' [via Packagist]',
                'private_repo' => ' [via Private Repo]',
            ];
            $sourceLabel = $sourceLabels[$source] ?? '';

            $report[] = sprintf("  %s  %-50s", $statusSymbol, $package);
            $report[] = sprintf("      Installed: %-20s  Latest: %s%s", $installed, $latest, $sourceLabel);

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
        $report[] = sprintf("Summary: %d up-to-date, %d updates available, %d unavailable, %d errors",
            $upToDateCount, $updateCount, $unavailableCount, $errorCount);
        $report[] = "";

        return implode("\n", $report);
    }
}
