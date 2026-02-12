# Installation & Setup Guide

## Overview

This is a Composer plugin that provides the `composer vendor:check` command. It works on any Composer project with zero configuration.

---

## Installation

### Per-Project (Recommended)

```bash
cd /path/to/your/project
composer require --dev getjohn/module-composer-vendor-checker
```

### Global Installation

```bash
composer global require getjohn/module-composer-vendor-checker
```

Now you can run `composer vendor:check` in any project.

### As a Path Repository

```bash
composer config repositories.vendor-checker path /path/to/module-composer-vendor-checker
composer require --dev getjohn/module-composer-vendor-checker
```

### Verify Installation

```bash
composer vendor:check --help
```

---

## First Run

### Basic Test

```bash
# Show help
composer vendor:check --help

# Check all installed packages
composer vendor:check

# Check a single vendor URL
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html
```

### Expected Output

```
Checking packages from: ./composer.lock

  [ 1/42] stripe/stripe-payments                          packagist OK
  [ 2/42] klaviyo/magento2-extension                      packagist UPDATE
  ...

  Vendor Version Check Report
  --------------------------------------------------------------------------

  ✓  stripe/stripe-payments
      Installed: 3.5.0              Latest: 3.5.0 [via Packagist]

  ↑  klaviyo/magento2-extension
      Installed: 4.4.2              Latest: 4.5.0 [via Packagist]

  --------------------------------------------------------------------------
  Summary: 35 up-to-date, 5 updates available, 0 unavailable, 2 errors
```

---

## Configuration

Configuration is **optional**. Without any config, all packages are checked via Packagist.

### Custom Config File

Create a PHP file based on `config/packages.php.example`:

```bash
cp vendor/getjohn/module-composer-vendor-checker/config/packages.php.example my-packages.php
```

Edit the file to add your skip lists, website overrides, and skip patterns, then run:

```bash
composer vendor:check --config=my-packages.php
```

### Key Configuration Options

```php
return [
    // Skip framework/core vendors entirely
    'skip_vendors' => ['magento', 'laminas', 'symfony'],

    // Skip specific packages
    'skip_packages' => ['my-agency/internal-module'],

    // Website overrides (checked before Packagist)
    'package_url_mappings' => [
        'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
    ],

    // Private repo hosts to skip
    'skip_hosts' => ['repo.magento.com'],

    // Host patterns to skip (regex)
    'skip_patterns' => ['/\.satis\./i'],
];
```

### Private Repositories

Private repos are auto-detected from your `composer.json` repository definitions and authenticated using your `auth.json` credentials. No manual configuration needed.

### Adding Custom Vendor Patterns

If you need website scraping support for a new vendor, add patterns to `VersionChecker.php`:

```php
'newvendor' => [
    'url_match' => 'newvendor.com',
    'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
    'changelog_pattern' => '/##\s*v?(\d+\.\d+\.\d+)\s*\(([^)]+)\)(.*?)(?=##|$)/s',
]
```

---

## Caching

Results are cached in `.vendor-check-cache/` in the lock file directory. Default TTL is 1 hour.

```bash
# Skip cache (force fresh check)
composer vendor:check --no-cache

# Clear cache before running
composer vendor:check --clear-cache

# Custom TTL
composer vendor:check --cache-ttl=7200
```

---

## CI/CD Integration

### GitHub Actions

```yaml
name: Check Vendor Versions
on:
  schedule:
    - cron: '0 9 * * 1'
  workflow_dispatch:

jobs:
  check-versions:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - run: composer install
      - run: composer vendor:check --format=json --output=versions.json
      - uses: actions/upload-artifact@v4
        with:
          name: version-report
          path: versions.json
```

### Automated Notifications

```bash
#!/bin/bash
UPDATES=$(composer vendor:check --format=json | \
  jq -r '.[] | select(.status=="UPDATE_AVAILABLE") |
  "\(.package): \(.installed_version) -> \(.latest_version)"')

if [ ! -z "$UPDATES" ]; then
    curl -X POST -H 'Content-type: application/json' \
      --data "{\"text\":\"Vendor updates available:\n$UPDATES\"}" \
      $SLACK_WEBHOOK_URL
fi
```

---

## Troubleshooting

### Command Not Found

```bash
composer dump-autoload
```

### No Results

If all packages are being skipped, check your `skip_vendors` list in the config. The default config skips common framework vendors (magento, laminas, symfony, etc.).

### Cloudflare Blocked

Vendors using Cloudflare cannot be scraped. The tool falls back to Packagist automatically. If the package isn't on Packagist, it reports as ERROR.

### SSL Certificate Errors

Update your CA certificates:

```bash
# Ubuntu/Debian
sudo apt-get update && sudo apt-get install ca-certificates

# macOS
brew install ca-certificates
```

---

## Uninstallation

```bash
composer remove getjohn/module-composer-vendor-checker
```

## Requirements

- PHP 7.4 or higher
- Composer 2.x
- ext-json
- guzzlehttp/guzzle ^6.5 or ^7.0
