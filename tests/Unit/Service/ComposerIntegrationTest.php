<?php

namespace GetJohn\VendorChecker\Tests\Unit\Service;

use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Service\ResultCache;
use GetJohn\VendorChecker\Service\VersionChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ComposerIntegrationTest extends TestCase
{
    /** @var string */
    private $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures';
    }

    /**
     * Create a VersionChecker with mocked HTTP responses.
     *
     * @param Response[] $responses
     * @return VersionChecker
     */
    private function createMockChecker(array $responses)
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        return new VersionChecker($client);
    }

    public function testGetInstalledPackagesReturnsAllPackages()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        // 7 packages in fixture lock file
        $this->assertCount(7, $packages);
        $this->assertArrayHasKey('amasty/promo', $packages);
        $this->assertArrayHasKey('klaviyo/magento2-extension', $packages);
        $this->assertArrayHasKey('magento/framework', $packages);
    }

    public function testSkippedVendorsAreMarkedAsSkip()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        // magento and laminas are in skip_vendors
        $this->assertEquals('skip', $packages['magento/framework']['method']);
        $this->assertEquals('skip', $packages['laminas/laminas-validator']['method']);
    }

    public function testSkippedPackagesAreMarkedAsSkip()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        $this->assertEquals('skip', $packages['getjohn/module-customsprice']['method']);
    }

    public function testWebsiteOverrideResolvedCorrectly()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        $this->assertEquals('website', $packages['amasty/promo']['method']);
    }

    public function testPrivateRepoResolvedCorrectly()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        // xtento has both a website override and a private repo — website takes precedence
        $this->assertEquals('website', $packages['xtento/orderexport']['method']);
    }

    public function testPackagistDefaultForUnknownPackage()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages();

        // stripe/stripe-payments has no website override, no private repo — defaults to packagist
        $this->assertEquals('packagist', $packages['stripe/stripe-payments']['method']);
    }

    public function testPackageFilterReducesResults()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        $packages = $integration->getInstalledPackages(['stripe/stripe-payments', 'amasty/promo']);

        $this->assertCount(2, $packages);
        $this->assertArrayHasKey('stripe/stripe-payments', $packages);
        $this->assertArrayHasKey('amasty/promo', $packages);
    }

    public function testCheckForUpdatesSkipsSkippedPackages()
    {
        // Mock: only packagist-resolvable packages will trigger HTTP calls.
        // stripe and klaviyo are Packagist, amasty and xtento are website.
        // We'll mock enough responses for warmCache + actual checks.
        $packagistResponse = json_encode([
            'packages' => [
                'stripe/stripe-payments' => [
                    ['version' => '3.6.0'],
                ],
            ],
        ]);

        // We need enough responses for warmCache (multiple URLs) + actual checks.
        // With 4 non-skipped packages (amasty, klaviyo, xtento, stripe), the warm cache
        // fetches multiple URLs. Let's provide generous 200 responses.
        $responses = [];
        for ($i = 0; $i < 20; $i++) {
            $responses[] = new Response(200, [], $packagistResponse);
        }

        $checker = $this->createMockChecker($responses);

        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php',
            null,
            $checker
        );

        $results = $integration->checkForUpdates();

        // 7 packages total, 3 skipped (magento, laminas, getjohn) = 4 results
        $this->assertCount(4, $results);

        // Verify skipped packages are not in results
        $resultPackages = array_column($results, 'package');
        $this->assertNotContains('magento/framework', $resultPackages);
        $this->assertNotContains('laminas/laminas-validator', $resultPackages);
        $this->assertNotContains('getjohn/module-customsprice', $resultPackages);
    }

    public function testCheckForUpdatesCacheHitAvoidsFetch()
    {
        $cacheDir = sys_get_temp_dir() . '/vendor-check-test-cache-' . uniqid();

        try {
            $cache = new ResultCache($cacheDir, 3600);
            $cache->set('stripe/stripe-payments', [
                'package' => 'stripe/stripe-payments',
                'installed_version' => '3.5.0',
                'latest_version' => '3.5.0',
                'status' => 'UP_TO_DATE',
                'source' => 'packagist',
            ]);
            $cache->flush();

            // Only need responses for non-cached packages
            $responses = [];
            for ($i = 0; $i < 20; $i++) {
                $responses[] = new Response(200, [], json_encode([
                    'packages' => ['x/y' => [['version' => '1.0.0']]],
                ]));
            }

            $checker = $this->createMockChecker($responses);

            $integration = new ComposerIntegration(
                $this->fixturesDir . '/composer.lock',
                $this->fixturesDir . '/composer.json',
                $this->fixturesDir . '/auth.json',
                $this->fixturesDir . '/packages.php',
                $cache,
                $checker
            );

            // Filter to only stripe to test cache hit
            $results = $integration->checkForUpdates(null, ['stripe/stripe-payments']);

            $this->assertCount(1, $results);
            $this->assertEquals('UP_TO_DATE', $results[0]['status']);
            $this->assertEquals('stripe/stripe-payments', $results[0]['package']);
        } finally {
            $cachePath = $cacheDir . '/results.json';
            if (file_exists($cachePath)) {
                unlink($cachePath);
            }
            if (is_dir($cacheDir)) {
                rmdir($cacheDir);
            }
        }
    }

    public function testCompareVersionsUpToDate()
    {
        $this->assertEquals('UP_TO_DATE', ComposerIntegration::compareVersions('1.0.0', '1.0.0'));
    }

    public function testCompareVersionsUpdateAvailable()
    {
        $this->assertEquals('UPDATE_AVAILABLE', ComposerIntegration::compareVersions('1.0.0', '2.0.0'));
    }

    public function testCompareVersionsAheadOfVendor()
    {
        $this->assertEquals('AHEAD_OF_VENDOR', ComposerIntegration::compareVersions('3.0.0', '2.0.0'));
    }

    public function testCompareVersionsStripsVPrefix()
    {
        $this->assertEquals('UP_TO_DATE', ComposerIntegration::compareVersions('v1.5.0', '1.5.0'));
        $this->assertEquals('UP_TO_DATE', ComposerIntegration::compareVersions('1.5.0', 'v1.5.0'));
    }

    public function testMissingLockFileThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('composer.lock not found');

        $integration = new ComposerIntegration('/nonexistent/path/composer.lock');
        $integration->getInstalledPackages();
    }

    public function testWorksWithoutExplicitConfig()
    {
        // No explicit config path, no composer.json, no auth.json
        // Bundled config/packages.php will still be loaded if it exists
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock'
        );

        $packages = $integration->getInstalledPackages();

        // All 7 packages from fixture lock file should be present
        $this->assertCount(7, $packages);
        $this->assertArrayHasKey('amasty/promo', $packages);
        $this->assertArrayHasKey('klaviyo/magento2-extension', $packages);
        $this->assertArrayHasKey('stripe/stripe-payments', $packages);

        // Packages not in any config should default to packagist
        $this->assertEquals('packagist', $packages['stripe/stripe-payments']['method']);
        $this->assertEquals('packagist', $packages['klaviyo/magento2-extension']['method']);
    }

    public function testPrivateRepoMapBuiltFromFixtures()
    {
        $integration = new ComposerIntegration(
            $this->fixturesDir . '/composer.lock',
            $this->fixturesDir . '/composer.json',
            $this->fixturesDir . '/auth.json',
            $this->fixturesDir . '/packages.php'
        );

        // amasty/promo comes from composer.amasty.com (private repo)
        $repoConfig = $integration->getPrivateRepoConfig('amasty/promo');
        $this->assertNotNull($repoConfig);
        $this->assertStringContainsString('amasty.com', $repoConfig['repo_url']);

        // xtento/orderexport comes from repo.xtento.com
        $repoConfig = $integration->getPrivateRepoConfig('xtento/orderexport');
        $this->assertNotNull($repoConfig);
        $this->assertStringContainsString('xtento.com', $repoConfig['repo_url']);

        // repo.magento.com is skipped
        $this->assertNull($integration->getPrivateRepoConfig('magento/framework'));

        // satis repos are skipped by pattern
        $this->assertNull($integration->getPrivateRepoConfig('getjohn/module-customsprice'));
    }
}
