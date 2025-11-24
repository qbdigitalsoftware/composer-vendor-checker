# Installation & Setup Guide

## Overview

This module works as **both** a Composer plugin and a Magento 2 module. You can install it either way (or both!).

---

## Method 1: Install as Composer Plugin (Recommended)

This makes the `composer vendor:check` command available globally or per-project.

### Global Installation (Available everywhere)

```bash
composer global require getjohn/magento2-vendor-checker
```

Now you can run `composer vendor:check` in any project!

### Per-Project Installation

```bash
cd /path/to/your/magento2/project
composer require getjohn/magento2-vendor-checker
```

The command will be available in this project.

### Verify Installation

```bash
composer vendor:check --help
```

You should see the help documentation for the command.

---

## Method 2: Install as Magento 2 Module

This integrates the module into your Magento 2 installation.

### Using Composer (Recommended)

```bash
cd /path/to/your/magento2/project
composer require getjohn/magento2-vendor-checker
php bin/magento module:enable GetJohn_VendorChecker
php bin/magento setup:upgrade
php bin/magento cache:flush
```

### Manual Installation

1. Create directory structure:
```bash
mkdir -p app/code/GetJohn/VendorChecker
```

2. Copy module files:
```bash
cp -r magento2-vendor-checker/* app/code/GetJohn/VendorChecker/
```

3. Enable the module:
```bash
php bin/magento module:enable GetJohn_VendorChecker
php bin/magento setup:upgrade
php bin/magento cache:flush
```

### Verify Installation

```bash
php bin/magento module:status GetJohn_VendorChecker
```

Should show as "enabled".

---

## First Run

### Test the Command

```bash
# Basic test
composer vendor:check --help

# Check a single vendor URL
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html

# Check all your installed packages
composer vendor:check
```

### Expected Output

```
╔════════════════════════════════════════════════════════════════════════════╗
║                        Vendor Version Check Report                         ║
╚════════════════════════════════════════════════════════════════════════════╝

  ✓  amasty/module-admin-actions-log
      Installed: 2.1.0              Latest: 2.1.0

  ↑  amasty/promo
      Installed: 2.22.0             Latest: 2.23.1

─────────────────────────────────────────────────────────────────────────────
Summary: 1 up-to-date, 1 updates available, 0 errors
```

---

## Configuration

### Adding Custom Vendor URLs

If you have modules from vendors not in the default list, add them:

#### Method A: Programmatically

Create a PHP script (e.g., `check-custom-vendors.php`):

```php
<?php
require 'vendor/autoload.php';

use GetJohn\VendorChecker\Service\ComposerIntegration;

$integration = new ComposerIntegration('./composer.lock');

// Add your custom mappings
$integration->addPackageUrlMapping(
    'customvendor/module-name',
    'https://customvendor.com/product-page.html'
);

$results = $integration->checkForUpdates(true);
echo $integration->generateReport($results);
```

Run it:
```bash
php check-custom-vendors.php
```

#### Method B: Edit the Source

Edit `src/Service/ComposerIntegration.php` and add to the `$packageUrlMappings` array:

```php
private $packageUrlMappings = [
    // Existing mappings...
    
    // Your custom mappings
    'customvendor/module-name' => 'https://customvendor.com/product.html',
    'anothervendor/extension' => 'https://anothervendor.com/extension.html',
];
```

### Adding Custom Vendor Patterns

If you need to support a completely new vendor with different HTML structure:

Edit `src/Service/VersionChecker.php` and add to the `$vendorPatterns` array:

```php
private $vendorPatterns = [
    // Existing patterns...
    
    'newvendor.com' => [
        'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
        'changelog_pattern' => '/##\s*v?(\d+\.\d+\.\d+)\s*\(([^)]+)\)(.*?)(?=##|$)/s',
        'composer_pattern' => '/composer\s+require\s+([\w\-\/]+)/i'
    ]
];
```

---

## Troubleshooting

### Command Not Found

**Problem**: `composer vendor:check` not recognized

**Solutions**:

1. Rebuild Composer autoload:
```bash
composer dump-autoload
```

2. Check if plugin is installed:
```bash
composer show getjohn/magento2-vendor-checker
```

3. Check Composer's plugin list:
```bash
composer global show
```

### No Packages Found

**Problem**: "No packages found to check"

**Possible causes**:

1. **No mapped vendor packages** - The module only checks packages it knows about
   
   Solution: Add custom URL mappings (see Configuration above)

2. **Wrong composer.lock path**
   
   Solution: Specify the correct path:
   ```bash
   composer vendor:check --path=/correct/path/to/composer.lock
   ```

3. **composer.lock doesn't exist**
   
   Solution: Run `composer install` first to generate it

### Version Mismatch Warnings

**Problem**: Tool shows version mismatch between sources

This is actually **working correctly**! It means:

- Composer has one version
- Marketplace has another version
- Vendor website shows yet another version

**Action**: Wait for all sources to sync, or contact the vendor.

### Vendor Website Changed

**Problem**: Can't extract version from a vendor's website

**Cause**: Vendor redesigned their website

**Solution**: Update the patterns in `VersionChecker.php` for that vendor. Check the HTML source of their product page and adjust the regex patterns accordingly.

### SSL Certificate Errors

**Problem**: SSL verification errors when fetching URLs

**Temporary fix** (not recommended for production):
```bash
# Disable SSL verification (security risk!)
composer config -g disable-tls false
```

**Proper fix**: Update your CA certificates:
```bash
# Ubuntu/Debian
sudo apt-get update && sudo apt-get install ca-certificates

# macOS
brew install ca-certificates

# Windows
# Update via Windows Update
```

---

## Advanced Usage

### CI/CD Integration

#### GitLab CI

```yaml
# .gitlab-ci.yml
vendor-version-check:
  stage: test
  script:
    - composer vendor:check --json > versions.json
    - |
      if jq -e '.[] | select(.status=="UPDATE_AVAILABLE")' versions.json; then
        echo "⚠️  Updates available - review versions.json artifact"
      fi
  artifacts:
    paths:
      - versions.json
    when: always
  allow_failure: true
```

#### GitHub Actions

```yaml
# .github/workflows/vendor-check.yml
name: Check Vendor Versions

on:
  schedule:
    - cron: '0 9 * * 1'  # Every Monday at 9 AM
  workflow_dispatch:

jobs:
  check-versions:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Install dependencies
        run: composer install
        
      - name: Check vendor versions
        run: composer vendor:check --json > versions.json
        
      - name: Upload results
        uses: actions/upload-artifact@v2
        with:
          name: version-report
          path: versions.json
```

### Automated Notifications

Create a script to notify your team:

```bash
#!/bin/bash
# notify-updates.sh

UPDATES=$(composer vendor:check --json | \
  jq -r '.[] | select(.status=="UPDATE_AVAILABLE") | 
  "\(.package): \(.installed_version) → \(.latest_version)"')

if [ ! -z "$UPDATES" ]; then
    # Send to Slack
    curl -X POST -H 'Content-type: application/json' \
      --data "{\"text\":\"Vendor updates available:\n$UPDATES\"}" \
      $SLACK_WEBHOOK_URL
fi
```

### Regular Reporting

Add to crontab:

```bash
# Check every Monday at 9 AM and email results
0 9 * * 1 cd /path/to/project && composer vendor:check | mail -s "Weekly Vendor Check" team@example.com
```

---

## Uninstallation

### Remove Composer Plugin

```bash
composer remove getjohn/magento2-vendor-checker
```

### Remove Magento 2 Module

```bash
php bin/magento module:disable GetJohn_VendorChecker
php bin/magento setup:upgrade
composer remove getjohn/magento2-vendor-checker
rm -rf app/code/GetJohn/VendorChecker
```

---

## Getting Help

1. Check this documentation
2. Review the examples in `examples/` directory
3. Check the source code - it's well-commented
4. Open an issue on GitHub
5. Contact: john@getjohn.co.uk

---

## What's Next?

Once installed, check out:

- **QUICKSTART.md** - Common command examples
- **README.md** - Full feature documentation  
- **examples/custom-config.php** - Customization examples
- **STRUCTURE.md** - Understanding the codebase
