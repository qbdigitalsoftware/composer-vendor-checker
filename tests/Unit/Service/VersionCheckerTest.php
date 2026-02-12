<?php

namespace GetJohn\VendorChecker\Tests\Unit\Service;

use GetJohn\VendorChecker\Service\VersionChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class VersionCheckerTest extends TestCase
{
    /**
     * Create a VersionChecker with a mocked HTTP client.
     *
     * @param Response[] $responses
     * @return VersionChecker
     */
    private function createCheckerWithMock(array $responses)
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        return new VersionChecker($client);
    }

    public function testGetPackagistVersionParsesP2Response()
    {
        $body = json_encode([
            'packages' => [
                'klaviyo/magento2-extension' => [
                    ['version' => '4.5.0'],
                    ['version' => '4.4.2'],
                    ['version' => 'dev-master'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        $version = $checker->getPackagistVersion('klaviyo/magento2-extension');
        $this->assertEquals('4.5.0', $version);
    }

    public function testGetPackagistVersionSkipsDevVersions()
    {
        $body = json_encode([
            'packages' => [
                'test/pkg' => [
                    ['version' => 'dev-master'],
                    ['version' => '2.0.0-beta1'],
                    ['version' => '1.5.0-RC1'],
                    ['version' => '1.4.0'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        $version = $checker->getPackagistVersion('test/pkg');
        $this->assertEquals('1.4.0', $version);
    }

    public function testGetPackagistVersionReturnsNullOn404()
    {
        $checker = $this->createCheckerWithMock([
            new Response(404, [], '{"error":"not found"}'),
        ]);

        $version = $checker->getPackagistVersion('nonexistent/package');
        $this->assertNull($version);
    }

    public function testGetPackagistVersionStripsVPrefix()
    {
        $body = json_encode([
            'packages' => [
                'test/pkg' => [
                    ['version' => 'v3.2.1'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        $version = $checker->getPackagistVersion('test/pkg');
        $this->assertEquals('3.2.1', $version);
    }

    public function testGetPackagistVersionReturnsNullForEmptyVersions()
    {
        $body = json_encode([
            'packages' => [
                'test/pkg' => [
                    ['version' => 'dev-master'],
                    ['version' => 'dev-develop'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        $version = $checker->getPackagistVersion('test/pkg');
        $this->assertNull($version);
    }

    public function testGetPrivateRepoVersionP2Endpoint()
    {
        $body = json_encode([
            'packages' => [
                'xtento/orderexport' => [
                    ['version' => '2.15.0'],
                    ['version' => '2.14.9'],
                    ['version' => '2.14.8'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        $version = $checker->getPrivateRepoVersion(
            'xtento/orderexport',
            'https://repo.xtento.com',
            ['username' => 'user', 'password' => 'pass']
        );

        $this->assertEquals('2.15.0', $version);
    }

    public function testGetPrivateRepoVersionFallsToNextEndpoint()
    {
        $body = json_encode([
            'packages' => [
                'xtento/orderexport' => [
                    ['version' => '2.15.0'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(404, [], ''),          // p2/ fails
            new Response(200, [], $body),        // p/ succeeds
        ]);

        $version = $checker->getPrivateRepoVersion(
            'xtento/orderexport',
            'https://repo.xtento.com',
            ['username' => 'user', 'password' => 'pass']
        );

        $this->assertEquals('2.15.0', $version);
    }

    public function testGetPrivateRepoVersionReturnsNullWhenAllFail()
    {
        $checker = $this->createCheckerWithMock([
            new Response(404, [], ''),
            new Response(404, [], ''),
            new Response(404, [], ''),
        ]);

        $version = $checker->getPrivateRepoVersion(
            'unknown/pkg',
            'https://repo.example.com',
            ['username' => 'user', 'password' => 'pass']
        );

        $this->assertNull($version);
    }

    public function testGetPrivateRepoVersionReturnsNullWithMissingAuth()
    {
        $checker = $this->createCheckerWithMock([]);

        $version = $checker->getPrivateRepoVersion(
            'test/pkg',
            'https://repo.example.com',
            []
        );

        $this->assertNull($version);
    }

    public function testWarmCachePopulatesCache()
    {
        $body = json_encode([
            'packages' => [
                'test/pkg' => [
                    ['version' => '1.0.0'],
                ],
            ],
        ]);

        $checker = $this->createCheckerWithMock([
            new Response(200, [], $body),
        ]);

        // Warm cache with the URL
        $checker->warmCache([
            'https://repo.packagist.org/p2/test/pkg.json' => [],
        ]);

        // Subsequent call should use cached response (no more mock responses needed)
        $version = $checker->getPackagistVersion('test/pkg');
        $this->assertEquals('1.0.0', $version);
    }

    public function testGetSupportedVendors()
    {
        $checker = new VersionChecker();
        $vendors = $checker->getSupportedVendors();

        $this->assertContains('amasty', $vendors);
        $this->assertContains('xtento', $vendors);
        $this->assertContains('mageplaza', $vendors);
    }

    public function testGetVendorFromPackage()
    {
        $checker = new VersionChecker();
        $this->assertEquals('amasty', $checker->getVendorFromPackage('amasty/promo'));
        $this->assertEquals('stripe', $checker->getVendorFromPackage('stripe/stripe-payments'));
    }
}
