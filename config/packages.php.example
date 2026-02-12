<?php
/**
 * Package tracking configuration for Vendor Version Checker.
 *
 * This file defines which packages are tracked, how they are checked,
 * and which private repository hosts are excluded from scanning.
 *
 * To customise: edit this file directly, or copy packages.php.example
 * back to packages.php to restore defaults.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Package URL Mappings (Vendor Website Scraping)
    |--------------------------------------------------------------------------
    |
    | Maps Composer package names to their vendor product page URLs.
    | These URLs are scraped using vendor-specific regex patterns defined
    | in VersionChecker.php to extract the latest available version.
    |
    | Format: 'vendor/package-name' => 'https://vendor-product-page-url'
    |
    */
    'package_url_mappings' => [
        // Amasty modules (Cloudflare-protected — will fall back to private repo or error)
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Packagist-Only Packages
    |--------------------------------------------------------------------------
    |
    | Packages that should be checked via the public Packagist API only.
    | These packages either have no vendor product page, or their vendor
    | website does not expose version information in a scrapable format.
    |
    */
    'packagist_packages' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Hosts
    |--------------------------------------------------------------------------
    |
    | Composer repository hostnames to exclude from private repo scanning.
    | These are typically Magento core repos or marketplace repos that
    | should not be queried for third-party package versions.
    |
    */
    'skip_hosts' => [
        'repo.magento.com',
        'marketplace.magento.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns to match against repository hostnames. Any host
    | matching one of these patterns will be excluded from private repo
    | scanning. Useful for excluding agency/client Satis repos that
    | host custom modules rather than third-party vendor packages.
    |
    */
    'skip_patterns' => [
        '/\.satis\./i',         // e.g. client.satis.agency.co.uk
        '/\.getjohn\./i',       // e.g. any getjohn internal repo
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Vendors
    |--------------------------------------------------------------------------
    |
    | Composer vendor prefixes to skip entirely. Packages from these vendors
    | will not be checked for updates. Typically used for framework/core
    | packages that are managed via the main platform upgrade process.
    |
    */
    'skip_vendors' => [
        'magento', 'laminas', 'symfony', 'monolog', 'psr',
        'phpunit', 'php-amqplib', 'colinmollenhour', 'composer',
        'doctrine', 'elasticsearch', 'guzzlehttp', 'league',
        'nikic', 'phpseclib', 'ramsey', 'sebastian', 'theseer',
        'webmozart', 'wikimedia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Packages
    |--------------------------------------------------------------------------
    |
    | Specific Composer package names to skip entirely. Use this for
    | individual packages that should not be checked regardless of vendor.
    |
    */
    'skip_packages' => [],
];
