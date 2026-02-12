<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Service;

use GetJohn\VendorChecker\Output\ProgressReporter;

/**
 * Service to integrate with Composer and check installed packages for available updates.
 *
 * Reads all packages from composer.lock, resolves check strategies via PackageResolver,
 * and checks each non-skipped package for available updates using Packagist, private
 * repos, or vendor websites.
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

    /** @var ResultCache|null */
    protected $cache;

    /** @var PackageResolver */
    protected $resolver;

    /** @var array Loaded config from packages.php */
    protected $config = [];

    /** @var array Cached private repo config: ['package' => ['repo_url' => '...', 'auth' => [...]]] */
    protected $privateRepoMap = [];

    /**
     * @param string $composerLockPath Path to composer.lock
     * @param string|null $composerJsonPath Path to composer.json for repo definitions
     * @param string|null $authJsonPath Path to auth.json for private repo credentials
     * @param string|null $configPath Path to packages.php config file
     * @param ResultCache|null $cache Optional result cache
     * @param VersionChecker|null $versionChecker Optional injected checker (for testing)
     */
    public function __construct(
        $composerLockPath = './composer.lock',
        $composerJsonPath = null,
        $authJsonPath = null,
        $configPath = null,
        $cache = null,
        $versionChecker = null
    ) {
        $this->composerLockPath = $composerLockPath;
        $this->composerJsonPath = $composerJsonPath;
        $this->authJsonPath = $authJsonPath;
        $this->versionChecker = $versionChecker ?: new VersionChecker();
        $this->cache = $cache;

        $this->config = $this->loadConfig($configPath);

        if ($composerJsonPath && $authJsonPath) {
            $this->buildPrivateRepoMap();
        }

        $this->resolver = new PackageResolver($this->config, $this->privateRepoMap);
    }

    /**
     * Load configuration from a PHP file.
     *
     * Search order:
     * 1. Explicit $configPath parameter
     * 2. Plugin's bundled config/packages.php
     *
     * @param string|null $configPath
     * @return array
     */
    protected function loadConfig($configPath)
    {
        if ($configPath !== null && file_exists($configPath)) {
            $config = require $configPath;
            if (is_array($config)) {
                return $config;
            }
        }

        $pluginConfig = dirname(__DIR__, 2) . '/config/packages.php';
        if (file_exists($pluginConfig)) {
            $config = require $pluginConfig;
            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * Build a map of packages to their private Composer repos + auth credentials.
     * Reads composer.json for repo URLs and auth.json for credentials.
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

        // Read skip hosts and patterns from config (with sensible defaults)
        $skipHosts = isset($this->config['skip_hosts']) && is_array($this->config['skip_hosts'])
            ? $this->config['skip_hosts']
            : ['repo.magento.com', 'marketplace.magento.com'];

        $skipPatterns = isset($this->config['skip_patterns']) && is_array($this->config['skip_patterns'])
            ? $this->config['skip_patterns']
            : ['/\.satis\./i', '/\.getjohn\./i'];

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

            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || in_array($host, $skipHosts)) {
                continue;
            }

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
     * Get private repo configuration for a package.
     *
     * @param string $packageName
     * @return array|null ['repo_url' => '...', 'auth' => ['username' => '...', 'password' => '...']]
     */
    public function getPrivateRepoConfig($packageName)
    {
        return $this->privateRepoMap[$packageName] ?? null;
    }

    /**
     * Get installed packages from composer.lock with resolution strategies.
     *
     * Returns ALL packages (not just pre-configured ones), each tagged with
     * a check method determined by PackageResolver.
     *
     * @param array $packageFilter Optional list of specific package names to include
     * @return array ['package/name' => ['method' => ..., 'version' => ..., ...], ...]
     */
    public function getInstalledPackages(array $packageFilter = [])
    {
        if (!file_exists($this->composerLockPath)) {
            throw new \Exception("composer.lock not found at: {$this->composerLockPath}");
        }

        $lockData = json_decode(file_get_contents($this->composerLockPath), true);

        if (!isset($lockData['packages'])) {
            throw new \Exception("Invalid composer.lock format");
        }

        $resolved = $this->resolver->resolveAll($lockData['packages']);

        if (!empty($packageFilter)) {
            $resolved = array_intersect_key($resolved, array_flip($packageFilter));
        }

        return $resolved;
    }

    /**
     * Check for updates for installed packages.
     *
     * @param ProgressReporter|null $progress Optional progress reporter
     * @param array $packageFilter Optional list of specific package names to check
     * @return array
     */
    public function checkForUpdates($progress = null, array $packageFilter = [])
    {
        $packages = $this->getInstalledPackages($packageFilter);

        // Separate cached hits from packages that need checking
        $results = [];
        $toCheck = [];

        foreach ($packages as $name => $pkg) {
            if ($pkg['method'] === 'skip') {
                continue;
            }

            if ($this->cache !== null) {
                $cached = $this->cache->get($name);
                if ($cached !== null) {
                    $results[] = $cached;
                    if ($progress !== null) {
                        $progress->advance($name, 'cached', $cached['status']);
                    }
                    continue;
                }
            }

            $toCheck[$name] = $pkg;
        }

        // Pre-fetch URLs concurrently for all cache misses
        $this->warmHttpCache($toCheck);

        // Check each uncached package
        foreach ($toCheck as $name => $pkg) {
            $result = $this->checkPackage($name, $pkg);
            $results[] = $result;

            if ($this->cache !== null) {
                $this->cache->set($name, $result);
            }

            if ($progress !== null) {
                $progress->advance($name, $pkg['method'], $result['status']);
            }
        }

        // Flush cache to disk
        if ($this->cache !== null) {
            $this->cache->flush();
        }

        return $results;
    }

    /**
     * Pre-fetch URLs concurrently for a set of packages.
     *
     * @param array $packages ['name' => ['method' => ..., ...], ...]
     */
    protected function warmHttpCache(array $packages)
    {
        $urlsToFetch = [];

        foreach ($packages as $name => $pkg) {
            $method = $pkg['method'];

            if ($method === 'website' && !empty($pkg['url'])) {
                $urlsToFetch[$pkg['url']] = [];
            }

            // Pre-fetch Packagist for packagist packages and as fallback for website/private_repo
            $urlsToFetch["https://repo.packagist.org/p2/{$name}.json"] = [];

            if ($method === 'private_repo' && isset($pkg['auth'])) {
                $repoUrl = rtrim($pkg['repo_url'], '/');
                $auth = $pkg['auth'];
                $urlsToFetch["{$repoUrl}/p2/{$name}.json"] = ['auth' => $auth, 'timeout' => 15];
                $urlsToFetch["{$repoUrl}/p/{$name}.json"] = ['auth' => $auth, 'timeout' => 15];
                $urlsToFetch["{$repoUrl}/packages.json"] = ['auth' => $auth, 'timeout' => 15];
            }
        }

        if (!empty($urlsToFetch)) {
            $this->versionChecker->warmCache($urlsToFetch);
        }
    }

    /**
     * Check a single package for updates using its resolved method.
     *
     * @param string $name Package name
     * @param array $pkg Resolution info from PackageResolver
     * @return array Result item
     */
    protected function checkPackage($name, array $pkg)
    {
        $method = $pkg['method'];
        $version = $pkg['version'];

        if ($method === 'packagist') {
            return $this->checkViaPackagist($name, $version);
        }

        if ($method === 'private_repo') {
            return $this->checkViaPrivateRepo($name, $version, $pkg);
        }

        if ($method === 'website') {
            return $this->checkViaWebsite($name, $version, $pkg['url']);
        }

        return [
            'package' => $name,
            'installed_version' => $version,
            'latest_version' => 'N/A',
            'status' => 'ERROR',
            'error' => "Unknown check method: {$method}",
        ];
    }

    /**
     * Check a package via Packagist API.
     *
     * @param string $name
     * @param string $installedVersion
     * @return array
     */
    protected function checkViaPackagist($name, $installedVersion)
    {
        $latestVersion = $this->versionChecker->getPackagistVersion($name);

        if ($latestVersion !== null) {
            return [
                'package' => $name,
                'installed_version' => $installedVersion,
                'latest_version' => $latestVersion,
                'status' => self::compareVersions($installedVersion, $latestVersion),
                'source' => 'packagist',
            ];
        }

        return [
            'package' => $name,
            'installed_version' => $installedVersion,
            'latest_version' => 'N/A',
            'status' => 'UNAVAILABLE',
            'source' => 'packagist',
            'error' => 'Package not found on Packagist',
        ];
    }

    /**
     * Check a package via private Composer repository with Packagist fallback.
     *
     * @param string $name
     * @param string $installedVersion
     * @param array $pkg Resolution info containing repo_url and auth
     * @return array
     */
    protected function checkViaPrivateRepo($name, $installedVersion, array $pkg)
    {
        $repoUrl = $pkg['repo_url'];
        $auth = $pkg['auth'];

        $latestVersion = $this->versionChecker->getPrivateRepoVersion($name, $repoUrl, $auth);

        if ($latestVersion !== null) {
            return [
                'package' => $name,
                'installed_version' => $installedVersion,
                'latest_version' => $latestVersion,
                'status' => self::compareVersions($installedVersion, $latestVersion),
                'source' => 'private_repo',
            ];
        }

        // Private repo failed — try Packagist as fallback
        $packagistVersion = $this->versionChecker->getPackagistVersion($name);
        if ($packagistVersion !== null) {
            return [
                'package' => $name,
                'installed_version' => $installedVersion,
                'latest_version' => $packagistVersion,
                'status' => self::compareVersions($installedVersion, $packagistVersion),
                'source' => 'packagist',
            ];
        }

        return [
            'package' => $name,
            'installed_version' => $installedVersion,
            'latest_version' => 'N/A',
            'status' => 'UNAVAILABLE',
            'source' => 'private_repo',
            'error' => 'Could not resolve version from private repo (auth may be expired)',
        ];
    }

    /**
     * Check a package via vendor website with Packagist fallback.
     *
     * @param string $name
     * @param string $installedVersion
     * @param string $url Vendor product page URL
     * @return array
     */
    protected function checkViaWebsite($name, $installedVersion, $url)
    {
        try {
            $vendorData = $this->versionChecker->getVendorVersion($url, $name);
            $latestVersion = $vendorData['latest_version'];

            if ($latestVersion === null) {
                return [
                    'package' => $name,
                    'installed_version' => $installedVersion,
                    'latest_version' => 'N/A',
                    'status' => 'UNAVAILABLE',
                    'source' => $vendorData['source'] ?? 'vendor_website',
                    'error' => 'No version information found on vendor page or Packagist',
                ];
            }

            $result = [
                'package' => $name,
                'installed_version' => $installedVersion,
                'latest_version' => $latestVersion,
                'status' => self::compareVersions($installedVersion, $latestVersion),
                'source' => $vendorData['source'] ?? 'vendor_website',
            ];

            if (!empty($vendorData['changelog'])) {
                $result['recent_changes'] = array_slice($vendorData['changelog'], 0, 3);
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'package' => $name,
                'installed_version' => $installedVersion,
                'latest_version' => 'Error',
                'status' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Compare two version strings.
     *
     * @param string $installed
     * @param string $latest
     * @return string UP_TO_DATE|UPDATE_AVAILABLE|AHEAD_OF_VENDOR
     */
    public static function compareVersions($installed, $latest)
    {
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
}
