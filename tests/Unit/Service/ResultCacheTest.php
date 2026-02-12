<?php

namespace GetJohn\VendorChecker\Tests\Unit\Service;

use GetJohn\VendorChecker\Service\ResultCache;
use PHPUnit\Framework\TestCase;

class ResultCacheTest extends TestCase
{
    /** @var string */
    private $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/vendor-check-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        $cachePath = $this->cacheDir . '/results.json';
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testSetAndGet()
    {
        $cache = new ResultCache($this->cacheDir, 3600);

        $result = ['package' => 'test/pkg', 'status' => 'UP_TO_DATE'];
        $cache->set('test/pkg', $result);
        $cache->flush();

        $retrieved = $cache->get('test/pkg');
        $this->assertEquals($result, $retrieved);
    }

    public function testGetReturnsNullForMissing()
    {
        $cache = new ResultCache($this->cacheDir, 3600);
        $this->assertNull($cache->get('nonexistent/package'));
    }

    public function testExpiredCacheReturnsNull()
    {
        $cache = new ResultCache($this->cacheDir, 1);

        $cache->set('test/pkg', ['status' => 'UP_TO_DATE']);
        $cache->flush();

        // Manipulate the cached_at timestamp to simulate expiry
        $path = $this->cacheDir . '/results.json';
        $data = json_decode(file_get_contents($path), true);
        $data['test/pkg']['cached_at'] = time() - 10;
        file_put_contents($path, json_encode($data));

        // Re-create cache to clear in-memory data
        $cache2 = new ResultCache($this->cacheDir, 1);
        $this->assertNull($cache2->get('test/pkg'));
    }

    public function testHasReturnsTrueForValid()
    {
        $cache = new ResultCache($this->cacheDir, 3600);
        $cache->set('test/pkg', ['status' => 'UP_TO_DATE']);
        $cache->flush();

        $this->assertTrue($cache->has('test/pkg'));
    }

    public function testHasReturnsFalseForMissing()
    {
        $cache = new ResultCache($this->cacheDir, 3600);
        $this->assertFalse($cache->has('missing/pkg'));
    }

    public function testClearRemovesAllEntries()
    {
        $cache = new ResultCache($this->cacheDir, 3600);
        $cache->set('pkg/a', ['status' => 'UP_TO_DATE']);
        $cache->set('pkg/b', ['status' => 'UPDATE_AVAILABLE']);
        $cache->flush();

        $cache->clear();
        $this->assertNull($cache->get('pkg/a'));
        $this->assertNull($cache->get('pkg/b'));
    }

    public function testCacheDirCreatedLazily()
    {
        $this->assertFalse(is_dir($this->cacheDir));

        $cache = new ResultCache($this->cacheDir, 3600);
        $cache->set('test/pkg', ['status' => 'UP_TO_DATE']);
        $cache->flush();

        $this->assertTrue(is_dir($this->cacheDir));
        $this->assertFileExists($this->cacheDir . '/results.json');
    }

    public function testFlushDoesNothingWhenNotDirty()
    {
        $cache = new ResultCache($this->cacheDir, 3600);
        $cache->flush();

        $this->assertFalse(is_dir($this->cacheDir));
    }

    public function testMultipleSetsOverwriteCorrectly()
    {
        $cache = new ResultCache($this->cacheDir, 3600);

        $cache->set('test/pkg', ['version' => '1.0.0']);
        $cache->set('test/pkg', ['version' => '2.0.0']);
        $cache->flush();

        $result = $cache->get('test/pkg');
        $this->assertEquals('2.0.0', $result['version']);
    }
}
