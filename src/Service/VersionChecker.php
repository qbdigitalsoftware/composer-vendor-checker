<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service to check vendor websites for module versions
 */
class VersionChecker
{
    /** @var Client */
    protected $httpClient;

    /** @var array Vendor-specific patterns keyed by composer vendor name */
    protected $vendorPatterns = [
        'amasty' => [ 'url_match' => 'amasty.com',
            'version_pattern' => '/Version\s+(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/<h3[^>]*>Version\s+(\d+\.\d+\.\d+)[^<]*<\/h3>\s*<p[^>]*>([^<]+)<\/p>/i',
            'changelog_section' => '/<div[^>]*class="[^"]*changelog[^"]*"[^>]*>(.*?)<\/div>/is'
        ],
        'mageplaza' => [ 'url_match' => 'mageplaza.com',
            'version_pattern' => '/v(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/##\s*v?(\d+\.\d+\.\d+)\s*\(([^)]+)\)(.*?)(?=##|$)/s',
            'composer_pattern' => '/composer\s+require\s+([\w\-\/]+)/i'
        ],
        'bsscommerce' => [ 'url_match' => 'bsscommerce.com',
            'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/<h4[^>]*>(\d+\.\d+\.\d+)[^<]*<\/h4>\s*<ul>(.*?)<\/ul>/is'
        ],
        'aheadworks' => [ 'url_match' => 'aheadworks.com',
            'version_pattern' => '/Version\s+(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/Release\s+(\d+\.\d+\.\d+)\s*-\s*([^<\n]+)/i'
        ],
        'mageme' => [ 'url_match' => 'mageme.com',
            'version_pattern' => '/(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/(\d+\.\d+\.\d+)\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d+\s+\d{4}/i',
            'changelog_section' => '/CHANGE\s+LOG(.*?)(?=Frequently|$)/is'
        ],
        'mageworx' => [ 'url_match' => 'mageworx.com',
            'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)\s*\(([^)]+)\)/i',
            'changelog_section' => '/<div[^>]*class="[^"]*changelog[^"]*"[^>]*>(.*?)<\/div>/is'
        ],
        'xtento' => [ 'url_match' => 'xtento.com',
            'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
            'changelog_pattern' => '/=====\s*(\d+\.\d+\.\d+)\s*=====\s*\*(.*?)(?======|$)/s',
            'changelog_section' => '/CHANGELOG(.*?)(?=This extension|$)/is'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; MagentoVersionChecker/1.0)'
            ]
        ]);
    }

    /**
     * Get list of supported vendor domains
     *
     * @return array
     */
    public function getSupportedVendors()
    {
        return array_keys($this->vendorPatterns);
    }

    /**
     * Get vendor name from package name (e.g., 'amasty' from 'amasty/promo')
     *
     * @param string $packageName
     * @return string|null
     */
    public function getVendorFromPackage($packageName)
    {
        $parts = explode('/', $packageName);
        return isset($parts[0]) ? $parts[0] : null;
    }

    /**
     * Get version information from a vendor website
     *
     * @param string $url
     * @return array
     * @throws \Exception
     */
    public function getVendorVersion($url)
    {
        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();

            $vendor = $this->detectVendor($url);
            $vendor_info = $this->vendorPatterns[$vendor];

            // Extract version
            $version = $this->extractVersion($html, $vendor_info);
            
            // Extract changelog
            $changelog = $this->extractChangelog($html, $vendor_info);

            return [
                'url' => $url,
                'vendor' => $vendor,
                'latest_version' => $version,
                'changelog' => $changelog,
                'checked_at' => date('Y-m-d H:i:s')
            ];

        } catch (GuzzleException $e) {
            throw new \Exception("Failed to fetch {$url}: " . $e->getMessage());
        }
    }

    /**
     * Check multiple packages from various sources
     *
     * @param array $packages
     * @param array $options
     * @return array
     */
    public function checkMultiplePackages(array $packages, array $options = [])
    {
        $results = [];
        
        foreach ($packages as $package) {
            $packageResult = [
                'package' => $package
            ];

            // Check Composer
            if (!empty($options['include_composer_show'])) {
                $packageResult['composer_version'] = $this->getComposerVersion($package);
            }

            // Check Marketplace
            if (!empty($options['include_marketplace'])) {
                $packageResult['marketplace_version'] = $this->getMarketplaceVersion($package);
            }

            // Check Vendor Site
            if (!empty($options['include_vendor_site']) && !empty($options['vendor_urls'][$package])) {
                try {
                    $vendorData = $this->getVendorVersion($options['vendor_urls'][$package]);
                    $packageResult['vendor_version'] = $vendorData['latest_version'];
                    $packageResult['vendor_url'] = $vendorData['url'];
                } catch (\Exception $e) {
                    $packageResult['vendor_version'] = 'Error: ' . $e->getMessage();
                }
            }

            // Compare versions
            if (isset($packageResult['composer_version'], $packageResult['marketplace_version'], $packageResult['vendor_version'])) {
                $packageResult['all_match'] = (
                    $packageResult['composer_version'] === $packageResult['marketplace_version'] &&
                    $packageResult['marketplace_version'] === $packageResult['vendor_version']
                );
            }

            $results[$package] = $packageResult;
        }

        return $results;
    }

    /**
     * Get version from composer show command
     *
     * @param string $package
     * @return string|null
     */
    protected function getComposerVersion($package, $publicOnly = false)
    {
	//TODO if($publicOnly) { .... somehow ignore auth.json and COMPOSER_AUTH }
        $command = "composer show {$package} 2>/dev/null | grep 'versions' | head -n1";
        $output = shell_exec($command);
        
        if ($output && preg_match('/\*\s*([0-9.]+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get version from Magento Marketplace
     *
     * @param string $package
     * @return string|null
     */
    protected function getMarketplaceVersion($package)
    {
        // Convert composer package name to marketplace URL
        $marketplaceUrl = $this->getMarketplaceUrl($package);
        
        if (!$marketplaceUrl) {
            return null;
        }

        try {
            $response = $this->httpClient->get($marketplaceUrl);
            $html = (string) $response->getBody();

            // Look for version in marketplace page
            if (preg_match('/Latest\s+Version[:\s]+v?(\d+\.\d+\.\d+)/i', $html, $matches)) {
                return $matches[1];
            }

            if (preg_match('/"version":\s*"([0-9.]+)"/', $html, $matches)) {
                return $matches[1];
            }

        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Convert composer package name to marketplace URL
     *
     * @param string $package
     * @return string|null
     */
    protected function getMarketplaceUrl($package)
    {
        // This is a simplified version - you may need to maintain a mapping
        $packageName = str_replace(['/', '-'], '', $package);
        return "https://commercemarketplace.adobe.com/{$packageName}.html";
    }

    /**
     * Detect vendor from URL
     *
     * @param string $url
     * @return string
     */
    protected function detectVendor($url)
    {
        foreach ($this->vendorPatterns as $vendor => $vendor_info) {
            if (strpos($url, $vendor_info['url_match']) !== false) {
                return $vendor;
            }
        }
        throw new \Exception("Unknown vendor for ".$url);
    }

    /**
     * Extract version from HTML
     *
     * @param string $html
     * @param array $vendor_info
     * @return string|null
     */
    protected function extractVersion($html, $vendor_info)
    {
        if (preg_match($vendor_info['version_pattern'], $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract changelog from HTML
     *
     * @param string $html
     * @param array $vendor_info
     * @return array
     */
    protected function extractChangelog($html, $vendor_info)
    {
        $changelog = [];

        // Try to find changelog section first
        if (isset($vendor_info['changelog_section']) && preg_match($vendor_info['changelog_section'], $html, $sectionMatch)) {
            $html = $sectionMatch[1];
        }

        // Extract changelog entries
        if (preg_match_all($vendor_info['changelog_pattern'], $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $entry = [
                    'version' => $match[1],
                    'date' => $match[2] ?? 'N/A',
                    'changes' => []
                ];

                // Extract individual changes if available
                if (isset($match[3])) {
                    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $match[3], $changes);
                    $entry['changes'] = array_map('strip_tags', $changes[1]);
                }

                $changelog[] = $entry;
            }
        }

        return $changelog;
    }
}
