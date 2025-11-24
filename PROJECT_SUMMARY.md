# Magento 2 Vendor Checker - Project Summary

## What We've Built

A complete Magento 2 module that adds a custom Composer command (`composer vendor:check`) to check vendor websites for the latest module versions. This solves the common problem where `composer update` doesn't show versions that are already available on vendor websites.

---

## Key Changes from Original CLI Tool

### Before (Standalone CLI Tool)
- Separate PHP scripts (`composer_amasty_checker.php`, etc.)
- Run with `php composer_amasty_checker.php [options]`
- No integration with Composer
- Manual execution

### After (Magento 2 Module + Composer Plugin)
- Integrated Composer plugin
- Run with `composer vendor:check [options]`
- Automatic Composer integration
- Professional module structure
- Both Magento 2 module AND Composer plugin

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│  User runs: composer vendor:check --packages=amasty/promo  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│               Composer Plugin System                        │
│  (ComposerPlugin.php → CommandProvider.php)                 │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│             VendorCheckCommand.php                          │
│  - Parses options (--packages, --url, --verbose, etc.)     │
│  - Coordinates between services                             │
│  - Formats output                                           │
└────────────────────────┬────────────────────────────────────┘
                         │
         ┌───────────────┴───────────────┐
         ▼                               ▼
┌──────────────────────┐      ┌──────────────────────┐
│ ComposerIntegration  │      │   VersionChecker     │
│                      │      │                      │
│ - Read composer.lock │      │ - HTTP client        │
│ - Package mappings   │      │ - Web scraping       │
│ - Version comparison │      │ - Pattern matching   │
│ - Report generation  │      │ - Changelog parsing  │
└──────────────────────┘      └──────────────────────┘
```

---

## File Structure

```
magento2-vendor-checker/
├── composer.json                    ← Composer plugin definition
├── registration.php                 ← Magento 2 registration
├── LICENSE                          ← MIT license
├── README.md                        ← Full documentation
├── QUICKSTART.md                    ← Quick start guide
├── INSTALL.md                       ← Installation guide
├── STRUCTURE.md                     ← Architecture docs
├── .gitignore
│
├── etc/
│   └── module.xml                   ← Magento 2 module config
│
├── src/
│   ├── ComposerPlugin.php           ← Plugin entry point
│   ├── CommandProvider.php          ← Command registration
│   ├── Command/
│   │   └── VendorCheckCommand.php   ← Main CLI command
│   └── Service/
│       ├── VersionChecker.php       ← Web scraping logic
│       └── ComposerIntegration.php  ← Composer integration
│
└── examples/
    └── custom-config.php            ← Configuration examples
```

---

## Usage Examples

### Basic Commands

```bash
# Check all installed packages
composer vendor:check

# Check specific packages
composer vendor:check --packages=amasty/promo,mageplaza/layered-navigation

# Check a single vendor URL
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html

# Verbose output with changelog
composer vendor:check -v

# Compare across all sources
composer vendor:check --compare-sources

# JSON output for automation
composer vendor:check --json
```

### Command Options Reference

| Option | Short | Description |
|--------|-------|-------------|
| `--path` | `-p` | Path to composer.lock (default: ./composer.lock) |
| `--packages` | - | Comma-separated package list |
| `--url` | `-u` | Single vendor URL to check |
| `--verbose` | `-v` | Show detailed output with changelog |
| `--compare-sources` | `-c` | Compare Composer/Marketplace/Vendor |
| `--json` | `-j` | JSON output for scripting |

---

## Supported Vendors

Out of the box support for:

- **Amasty** (amasty.com)
- **Mageplaza** (mageplaza.com)
- **BSS Commerce** (bsscommerce.com)
- **Aheadworks** (aheadworks.com)

Easily extensible for additional vendors by adding URL mappings and patterns.

---

## Key Features

### 1. Multi-Source Version Checking
Checks three sources:
- Composer (`composer show`)
- Magento Marketplace
- Vendor's website

Identifies sync issues and shows which source has the newest version.

### 2. Intelligent Web Scraping
- Vendor-specific patterns for different HTML structures
- Robust regex for version extraction
- Changelog parsing
- Error handling
- **Automatic vendor filtering** - only checks packages from vendors with defined patterns

### 3. Smart Package Filtering
When checking all packages, the tool automatically:
- Only includes vendors with defined scraping patterns (Amasty, Mageplaza, BSS, Aheadworks)
- Skips packages from unsupported vendors
- Shows supported vendors in verbose mode
- Prevents unnecessary HTTP requests and errors

### 4. Flexible Output
- Human-readable reports
- Verbose mode with changelogs
- JSON for automation
- Status indicators (✓ ↑ ⚠ ✗)

### 4. Composer Integration
- Native Composer command
- Reads composer.lock automatically
- Works with existing Composer workflows
- No separate tools needed

### 5. Dual Identity
Works as both:
- Composer plugin (for the command)
- Magento 2 module (for framework integration)

---

## Installation Options

### Option 1: Composer Plugin (Recommended)
```bash
composer require getjohn/magento2-vendor-checker
composer vendor:check
```

### Option 2: Magento 2 Module
```bash
# Via Composer
composer require getjohn/magento2-vendor-checker
php bin/magento module:enable GetJohn_VendorChecker
php bin/magento setup:upgrade

# Or manually to app/code/GetJohn/VendorChecker/
```

---

## Real-World Use Cases

### 1. Pre-Update Planning
```bash
# See what's available before running composer update
composer vendor:check --compare-sources
```

### 2. Weekly Update Reports
```bash
# Add to CI/CD or cron
composer vendor:check --json > weekly-report.json
```

### 3. Debugging Version Issues
```bash
# When a package won't update
composer vendor:check -v --packages=amasty/promo
```

### 4. Team Notifications
```bash
# Integration with Slack/Teams
UPDATES=$(composer vendor:check --json | jq -r '.[] | select(.status=="UPDATE_AVAILABLE")')
# Send $UPDATES to team chat
```

---

## Customization

### Adding Package URLs

```php
use GetJohn\VendorChecker\Service\ComposerIntegration;

$integration = new ComposerIntegration();
$integration->addPackageUrlMapping(
    'vendor/package',
    'https://vendor.com/product.html'
);
```

### Adding Vendor Patterns

Edit `src/Service/VersionChecker.php`:

```php
private $vendorPatterns = [
    'newvendor.com' => [
        'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
        'changelog_pattern' => '/##\s*(\d+\.\d+\.\d+)\s*\(([^)]+)\)/i',
    ]
];
```

---

## Technical Details

### Requirements
- PHP 7.4+
- Magento 2.4.x+
- Composer 2.x
- ext-dom
- ext-json
- guzzlehttp/guzzle ^7.0

### Type Classification
- **Composer type**: `composer-plugin`
- **Magento type**: `magento2-module`

### PSR-4 Autoloading
```json
"autoload": {
    "psr-4": {
        "GetJohn\\VendorChecker\\": "src/"
    }
}
```

---

## Testing Recommendations

### Manual Testing
```bash
# Test basic functionality
composer vendor:check --help

# Test with known package
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html

# Test JSON output
composer vendor:check --json | jq .

# Test error handling
composer vendor:check --url=https://invalid-url.com
```

### Integration Testing
```php
// Test in a PHP script
<?php
use GetJohn\VendorChecker\Service\VersionChecker;

$checker = new VersionChecker();
$result = $checker->getVendorVersion('https://amasty.com/...');
assert($result['latest_version'] !== null);
```

---

## Future Enhancements

Possible additions:

1. **More Vendors** - Add support for additional Magento extension vendors
2. **Cache Layer** - Cache vendor website responses to reduce HTTP requests
3. **Update Notifications** - Email/Slack notifications for new versions
4. **Auto-PR Creation** - Automatically create PRs when updates are found
5. **Version History** - Track version changes over time
6. **Security Advisories** - Check for security updates
7. **Dependency Analysis** - Show which modules depend on updated packages

---

## Documentation Files

All documentation included:

- **README.md** - Complete feature documentation
- **INSTALL.md** - Detailed installation guide
- **QUICKSTART.md** - Quick reference for common tasks
- **STRUCTURE.md** - Architecture and code organization
- **LICENSE** - MIT license
- **examples/custom-config.php** - Customization examples

---

## Support & Contact

- **Author**: John @ GetJohn
- **Website**: https://getjohn.co.uk
- **Email**: john@getjohn.co.uk
- **License**: MIT

---

## Migration from Old CLI Tool

If you have the old standalone scripts:

### Before
```bash
php composer_amasty_checker.php --packages=amasty/promo
```

### After
```bash
composer vendor:check --packages=amasty/promo
```

All functionality preserved, plus:
- Better integration
- More professional structure
- Easier to maintain
- Automatic Composer integration
- No need to remember script names

---

## Summary

This module transforms the original standalone PHP scripts into a professional, well-integrated Composer plugin that seamlessly adds vendor version checking to your Magento 2 workflow. It maintains all the original functionality while providing better structure, documentation, and usability.

The `composer vendor:check` command is now a first-class citizen in your Composer toolbox!
