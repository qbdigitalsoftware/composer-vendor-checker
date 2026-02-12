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
            'version_pattern' => '/Version:\s*(\d+\.\d+\.\d+)/',
            'version_select' => 'highest',
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
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
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
     * Falls back to Packagist API if website scraping fails or returns no version
     *
     * @param string $url
     * @param string|null $packageName Composer package name for Packagist fallback
     * @return array
     * @throws \Exception
     */
    public function getVendorVersion($url, $packageName = null)
    {
        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();

            $vendor = $this->detectVendor($url);
            $vendor_info = $this->vendorPatterns[$vendor];

            // Extract version
            $version = $this->extractVersion($html, $vendor_info);
            $source = 'vendor_website';

            // Extract changelog
            $changelog = $this->extractChangelog($html, $vendor_info);

            // If website returned no version, try Packagist as fallback
            if ($version === null && $packageName !== null) {
                $version = $this->getPackagistVersion($packageName);
                if ($version !== null) {
                    $source = 'packagist';
                }
            }

            return [
                'url' => $url,
                'vendor' => $vendor,
                'latest_version' => $version,
                'changelog' => $changelog,
                'source' => $source,
                'checked_at' => date('Y-m-d H:i:s')
            ];

        } catch (GuzzleException $e) {
            // Website unreachable (e.g., Cloudflare block) — try Packagist fallback
            if ($packageName !== null) {
                $version = $this->getPackagistVersion($packageName);
                if ($version !== null) {
                    return [
                        'url' => $url,
                        'vendor' => $this->getVendorFromPackage($packageName) ?? 'unknown',
                        'latest_version' => $version,
                        'changelog' => [],
                        'source' => 'packagist',
                        'checked_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            // Detect Cloudflare protection
            $message = $e->getMessage();
            if (strpos($message, '403') !== false && strpos($message, 'Just a moment') !== false) {
                throw new \Exception("Cloudflare protection detected on {$url} — website requires browser verification");
            }

            throw new \Exception("Failed to fetch {$url}: " . $message);
        }
    }

    /**
     * Get latest stable version from a private Composer repository (Satis)
     *
     * @param string $packageName e.g. 'amasty/promo'
     * @param string $repoUrl Base URL of the Composer repo (e.g. 'https://composer.amasty.com/enterprise/')
     * @param array $auth ['username' => '...', 'password' => '...']
     * @return string|null
     */
    public function getPrivateRepoVersion($packageName, $repoUrl, array $auth)
    {
        if (!isset($auth['username'], $auth['password'])) {
            return null;
        }
        $repoUrl = rtrim($repoUrl, '/');

        // Try Composer V2 provider format first: p2/{vendor}/{package}.json
        $endpoints = [
            "{$repoUrl}/p2/{$packageName}.json",
            "{$repoUrl}/p/{$packageName}.json",
            "{$repoUrl}/packages.json",
        ];

        $authOptions = [
            'auth' => [$auth['username'], $auth['password']],
            'timeout' => 15,
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $body = (string) $this->httpClient->get($endpoint, $authOptions)->getBody();
                $data = json_decode($body, true);
                if (!$data) {
                    continue;
                }

                // Direct package endpoint (p2/ or p/ format)
                if (isset($data['packages'][$packageName])) {
                    return $this->extractLatestStableFromRepo($data['packages'][$packageName]);
                }

                // Full packages.json — look for our package inside
                if (isset($data['packages']) && is_array($data['packages'])) {
                    foreach ($data['packages'] as $name => $versions) {
                        if ($name === $packageName) {
                            return $this->extractLatestStableFromRepo($versions);
                        }
                    }
                }

                // Satis with provider includes
                if (isset($data['provider-includes']) || isset($data['providers-url'])) {
                    $version = $this->resolveFromSatisProviders($data, $packageName, $repoUrl, $auth);
                    if ($version !== null) {
                        return $version;
                    }
                }

            } catch (\Exception $e) {
                // Try next endpoint
                continue;
            }
        }

        return null;
    }

    /**
     * Extract latest stable version from a Composer repo version list
     *
     * @param array $versions Array of version entries from packages.json
     * @return string|null
     */
    protected function extractLatestStableFromRepo(array $versions)
    {
        $stableVersions = [];

        foreach ($versions as $key => $release) {
            // Handle both indexed arrays and version-keyed arrays
            $version = is_array($release) ? ($release['version'] ?? $key) : $key;
            $version = ltrim($version, 'v');

            // Skip dev/alpha/beta/RC
            if (preg_match('/dev|alpha|beta|rc/i', $version)) {
                continue;
            }

            if (preg_match('/^\d+\.\d+(\.\d+)?/', $version, $m)) {
                $stableVersions[] = $m[0];
            }
        }

        if (empty($stableVersions)) {
            return null;
        }

        usort($stableVersions, 'version_compare');
        return end($stableVersions);
    }

    /**
     * Resolve version from Satis provider includes
     *
     * @param array $rootData Root packages.json data
     * @param string $packageName
     * @param string $repoUrl
     * @param array $auth
     * @return string|null
     */
    protected function resolveFromSatisProviders(array $rootData, $packageName, $repoUrl, array $auth)
    {
        $authOptions = [
            'auth' => [$auth['username'], $auth['password']],
            'timeout' => 15,
        ];

        if (isset($rootData['provider-includes'])) {
            foreach ($rootData['provider-includes'] as $template => $meta) {
                $hash = $meta['sha256'] ?? '';
                $url = $repoUrl . '/' . str_replace('%hash%', $hash, $template);

                try {
                    $body = (string) $this->httpClient->get($url, $authOptions)->getBody();
                    $providers = json_decode($body, true);

                    if (isset($providers['providers'][$packageName])) {
                        $pkgHash = $providers['providers'][$packageName]['sha256'] ?? '';
                        $providersUrl = $rootData['providers-url'] ?? '/p/%package%$%hash%.json';
                        $pkgUrl = $repoUrl . '/' . str_replace(
                            ['%package%', '%hash%'],
                            [$packageName, $pkgHash],
                            ltrim($providersUrl, '/')
                        );

                        $pkgBody = (string) $this->httpClient->get($pkgUrl, $authOptions)->getBody();
                        $pkgData = json_decode($pkgBody, true);

                        if (isset($pkgData['packages'][$packageName])) {
                            return $this->extractLatestStableFromRepo($pkgData['packages'][$packageName]);
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Get latest stable version from Packagist API
     *
     * @param string $packageName e.g. 'amasty/promo'
     * @return string|null
     */
    public function getPackagistVersion($packageName)
    {
        try {
            $response = $this->httpClient->get(
                "https://repo.packagist.org/p2/{$packageName}.json"
            );
            $data = json_decode((string) $response->getBody(), true);

            if (!isset($data['packages'][$packageName])) {
                return null;
            }

            // Versions are sorted newest-first; find the first stable release
            foreach ($data['packages'][$packageName] as $release) {
                $version = $release['version'] ?? '';

                // Skip dev/alpha/beta/RC versions
                if (preg_match('/dev|alpha|beta|rc/i', $version)) {
                    continue;
                }

                $version = ltrim($version, 'v');

                if (preg_match('/^\d+\.\d+(\.\d+)?/', $version)) {
                    return $version;
                }
            }
        } catch (\Exception $e) {
            // Packagist unavailable or package not found — silent fail
        }

        return null;
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
        $content = $html;

        // Apply section filter to isolate relevant content
        if (isset($vendor_info['section_filter'])) {
            if (preg_match($vendor_info['section_filter'], $html, $sectionMatch)) {
                $content = $sectionMatch[0];
            }
        }

        // If version_select is 'highest', find all matches and return the highest version
        if (isset($vendor_info['version_select']) && $vendor_info['version_select'] === 'highest') {
            if (preg_match_all($vendor_info['version_pattern'], $content, $matches)) {
                $versions = $matches[1];
                usort($versions, 'version_compare');
                return end($versions);
            }
            return null;
        }

        if (preg_match($vendor_info['version_pattern'], $content, $matches)) {
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
