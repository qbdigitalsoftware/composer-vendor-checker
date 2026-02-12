<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Service;

/**
 * File-based result cache with configurable TTL.
 * Stores results in a single JSON file to avoid excessive filesystem operations.
 */
class ResultCache
{
    /** @var string Directory for cache files */
    private $cacheDir;

    /** @var int TTL in seconds */
    private $ttl;

    /** @var array|null In-memory cache data (loaded lazily) */
    private $data;

    /** @var bool Whether data has been modified and needs writing */
    private $dirty = false;

    /**
     * @param string $cacheDir Absolute path to cache directory
     * @param int $ttl Cache TTL in seconds (default 3600 = 1 hour)
     */
    public function __construct($cacheDir, $ttl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->ttl = $ttl;
    }

    /**
     * Get a cached result for a package.
     *
     * @param string $packageName
     * @return array|null Result array, or null if not cached or expired
     */
    public function get($packageName)
    {
        $this->load();

        if (!isset($this->data[$packageName])) {
            return null;
        }

        $entry = $this->data[$packageName];
        $cachedAt = $entry['cached_at'];

        if ((time() - $cachedAt) > $this->ttl) {
            return null;
        }

        return $entry['result'];
    }

    /**
     * Store a result for a package.
     *
     * @param string $packageName
     * @param array $result
     */
    public function set($packageName, array $result)
    {
        $this->load();
        $this->data[$packageName] = [
            'result' => $result,
            'cached_at' => time(),
        ];
        $this->dirty = true;
    }

    /**
     * Check if a valid (non-expired) cache entry exists.
     *
     * @param string $packageName
     * @return bool
     */
    public function has($packageName)
    {
        return $this->get($packageName) !== null;
    }

    /**
     * Clear all cached results.
     */
    public function clear()
    {
        $path = $this->getCachePath();
        if (file_exists($path)) {
            unlink($path);
        }
        $this->data = [];
        $this->dirty = false;
    }

    /**
     * Persist any modified data to disk.
     * Call this after all set() operations are complete.
     */
    public function flush()
    {
        if (!$this->dirty) {
            return;
        }

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        file_put_contents(
            $this->getCachePath(),
            json_encode($this->data, JSON_PRETTY_PRINT)
        );

        $this->dirty = false;
    }

    /**
     * Load cache data from disk if not already loaded.
     */
    private function load()
    {
        if ($this->data !== null) {
            return;
        }

        $path = $this->getCachePath();
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $this->data = json_decode($json, true) ?: [];
        } else {
            $this->data = [];
        }
    }

    /**
     * Get the full path to the cache file.
     *
     * @return string
     */
    private function getCachePath()
    {
        return $this->cacheDir . '/results.json';
    }
}
