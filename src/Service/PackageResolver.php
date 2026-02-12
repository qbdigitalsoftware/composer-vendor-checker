<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Service;

/**
 * Determines the check strategy for each package.
 *
 * Resolution order:
 * 1. skip_vendors / skip_packages → skip
 * 2. package_url_mappings → website (with Packagist fallback)
 * 3. privateRepoMap → private_repo (with Packagist fallback)
 * 4. Default → packagist (auto-discovery)
 */
class PackageResolver
{
    /** @var array Website URL overrides from config: ['package/name' => 'https://...'] */
    private $websiteOverrides;

    /** @var array Private repo map: ['package/name' => ['repo_url' => ..., 'auth' => ...]] */
    private $privateRepoMap;

    /** @var array Vendor prefixes to skip entirely */
    private $skipVendors;

    /** @var array Specific package names to skip */
    private $skipPackages;

    /**
     * @param array $config Config from packages.php
     * @param array $privateRepoMap Private repo map from ComposerIntegration
     */
    public function __construct(array $config = [], array $privateRepoMap = [])
    {
        $this->websiteOverrides = isset($config['package_url_mappings']) && is_array($config['package_url_mappings'])
            ? $config['package_url_mappings']
            : [];
        $this->skipVendors = isset($config['skip_vendors']) && is_array($config['skip_vendors'])
            ? $config['skip_vendors']
            : [];
        $this->skipPackages = isset($config['skip_packages']) && is_array($config['skip_packages'])
            ? $config['skip_packages']
            : [];
        $this->privateRepoMap = $privateRepoMap;
    }

    /**
     * Determine the check strategy for a single package.
     *
     * @param string $packageName Composer package name (e.g. 'amasty/promo')
     * @return array ['method' => 'packagist'|'private_repo'|'website'|'skip', 'url' => '...']
     */
    public function resolve($packageName)
    {
        $vendor = $this->getVendor($packageName);

        // 1. Check skip lists
        if (in_array($packageName, $this->skipPackages, true)) {
            return ['method' => 'skip', 'reason' => 'skip_packages'];
        }
        if (in_array($vendor, $this->skipVendors, true)) {
            return ['method' => 'skip', 'reason' => 'skip_vendors'];
        }

        // 2. Check website overrides
        if (isset($this->websiteOverrides[$packageName])) {
            return [
                'method' => 'website',
                'url' => $this->websiteOverrides[$packageName],
            ];
        }

        // 3. Check private repo map
        if (isset($this->privateRepoMap[$packageName])) {
            return [
                'method' => 'private_repo',
                'repo_url' => $this->privateRepoMap[$packageName]['repo_url'],
                'auth' => $this->privateRepoMap[$packageName]['auth'],
            ];
        }

        // 4. Default to Packagist auto-discovery
        return ['method' => 'packagist'];
    }

    /**
     * Resolve all packages from a composer.lock packages array.
     *
     * @param array $lockPackages The 'packages' array from composer.lock
     * @return array ['package/name' => ['method' => ..., 'version' => ..., ...], ...]
     */
    public function resolveAll(array $lockPackages)
    {
        $resolved = [];

        foreach ($lockPackages as $pkg) {
            $name = $pkg['name'];
            $resolution = $this->resolve($name);
            $resolution['name'] = $name;
            $resolution['version'] = $pkg['version'];
            $resolved[$name] = $resolution;
        }

        return $resolved;
    }

    /**
     * Extract vendor prefix from a package name.
     *
     * @param string $packageName
     * @return string
     */
    private function getVendor($packageName)
    {
        $parts = explode('/', $packageName);
        return $parts[0];
    }
}
