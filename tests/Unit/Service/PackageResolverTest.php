<?php

namespace GetJohn\VendorChecker\Tests\Unit\Service;

use GetJohn\VendorChecker\Service\PackageResolver;
use PHPUnit\Framework\TestCase;

class PackageResolverTest extends TestCase
{
    private function createResolver(array $configOverrides = [], array $privateRepoMap = [])
    {
        $config = array_merge([
            'package_url_mappings' => [
                'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
            ],
            'skip_vendors' => ['magento', 'laminas'],
            'skip_packages' => ['getjohn/module-customsprice'],
        ], $configOverrides);

        return new PackageResolver($config, $privateRepoMap);
    }

    public function testResolveDefaultsToPackagist()
    {
        $resolver = $this->createResolver();
        $result = $resolver->resolve('klaviyo/magento2-extension');

        $this->assertEquals('packagist', $result['method']);
    }

    public function testResolveWebsiteOverride()
    {
        $resolver = $this->createResolver();
        $result = $resolver->resolve('amasty/promo');

        $this->assertEquals('website', $result['method']);
        $this->assertEquals('https://amasty.com/special-promotions-for-magento-2.html', $result['url']);
    }

    public function testResolvePrivateRepo()
    {
        $privateRepoMap = [
            'xtento/orderexport' => [
                'repo_url' => 'https://repo.xtento.com',
                'auth' => ['username' => 'user', 'password' => 'pass'],
            ],
        ];

        $resolver = $this->createResolver([], $privateRepoMap);
        $result = $resolver->resolve('xtento/orderexport');

        $this->assertEquals('private_repo', $result['method']);
        $this->assertEquals('https://repo.xtento.com', $result['repo_url']);
    }

    public function testResolveSkipVendor()
    {
        $resolver = $this->createResolver();
        $result = $resolver->resolve('magento/framework');

        $this->assertEquals('skip', $result['method']);
        $this->assertEquals('skip_vendors', $result['reason']);
    }

    public function testResolveSkipPackage()
    {
        $resolver = $this->createResolver();
        $result = $resolver->resolve('getjohn/module-customsprice');

        $this->assertEquals('skip', $result['method']);
        $this->assertEquals('skip_packages', $result['reason']);
    }

    public function testSkipPackageTakesPrecedenceOverWebsite()
    {
        $resolver = $this->createResolver([
            'package_url_mappings' => ['getjohn/module-customsprice' => 'https://example.com'],
            'skip_packages' => ['getjohn/module-customsprice'],
        ]);

        $result = $resolver->resolve('getjohn/module-customsprice');
        $this->assertEquals('skip', $result['method']);
    }

    public function testWebsiteOverrideTakesPrecedenceOverPrivateRepo()
    {
        $privateRepoMap = [
            'amasty/promo' => [
                'repo_url' => 'https://composer.amasty.com/enterprise/',
                'auth' => ['username' => 'user', 'password' => 'pass'],
            ],
        ];

        $resolver = $this->createResolver([], $privateRepoMap);
        $result = $resolver->resolve('amasty/promo');

        $this->assertEquals('website', $result['method']);
    }

    public function testResolveAllReturnsAllPackages()
    {
        $resolver = $this->createResolver();

        $lockPackages = [
            ['name' => 'amasty/promo', 'version' => '2.12.0'],
            ['name' => 'klaviyo/magento2-extension', 'version' => '4.4.2'],
            ['name' => 'magento/framework', 'version' => '103.0.7'],
        ];

        $results = $resolver->resolveAll($lockPackages);

        $this->assertCount(3, $results);
        $this->assertEquals('website', $results['amasty/promo']['method']);
        $this->assertEquals('packagist', $results['klaviyo/magento2-extension']['method']);
        $this->assertEquals('skip', $results['magento/framework']['method']);
        $this->assertEquals('2.12.0', $results['amasty/promo']['version']);
    }

    public function testEmptyConfigDefaultsToPackagist()
    {
        $resolver = new PackageResolver();
        $result = $resolver->resolve('some/random-package');

        $this->assertEquals('packagist', $result['method']);
    }

    public function testResolveAllPreservesVersions()
    {
        $resolver = $this->createResolver();

        $lockPackages = [
            ['name' => 'klaviyo/magento2-extension', 'version' => '4.4.2'],
        ];

        $results = $resolver->resolveAll($lockPackages);
        $this->assertEquals('4.4.2', $results['klaviyo/magento2-extension']['version']);
        $this->assertEquals('klaviyo/magento2-extension', $results['klaviyo/magento2-extension']['name']);
    }
}
