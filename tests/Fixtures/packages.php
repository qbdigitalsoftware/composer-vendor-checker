<?php
/**
 * Test fixture — minimal package configuration
 */
return [
    'package_url_mappings' => [
        'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
        'xtento/orderexport' => 'https://www.xtento.com/magento-extensions/magento-order-export-module.html',
    ],
    'packagist_packages' => [
        'klaviyo/magento2-extension',
    ],
    'skip_hosts' => [
        'repo.magento.com',
    ],
    'skip_patterns' => [
        '/\.satis\./i',
    ],
    'skip_vendors' => [
        'magento',
        'laminas',
    ],
    'skip_packages' => [
        'getjohn/module-customsprice',
    ],
];
