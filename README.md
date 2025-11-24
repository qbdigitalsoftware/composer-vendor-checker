# Magento 2 Vendor Version Checker

A Magento 2 module that adds a `composer vendor:check` command to check vendor websites for the latest module versions, helping you identify when updates are available that might not yet be reflected in Composer or the Magento Marketplace.

## The Problem This Solves

Sometimes `composer update` doesn't show the latest version that's advertised on a vendor's website. This can happen when:

- The vendor hasn't pushed the update to Packagist yet
- The Magento Marketplace listing is out of sync
- There's a delay in the Composer repository cache

This tool checks **all three sources** (Composer, Marketplace, and vendor websites) to give you the complete picture.

## Features

- ✅ Custom Composer command: `composer vendor:check`
- ✅ Check all installed vendor modules at once (only from supported vendors)
- ✅ Check specific packages by name
- ✅ Check a single vendor URL directly
- ✅ Compare versions across Composer, Marketplace, and vendor sites
- ✅ Detailed changelog information
- ✅ JSON output for automation/CI pipelines
- ✅ Support for major vendors: Amasty, Mageplaza, BSS Commerce, Aheadworks, MageMe, Mageworx, XTENTO
- ✅ Automatically filters to only vendors with defined patterns

## Installation

### Via Composer (Recommended)

```bash
composer require getjohn/magento2-vendor-checker
```

### Manual Installation

1. Clone or download this repository
2. Place in `app/code/GetJohn/VendorChecker`
3. Run Magento setup:

```bash
php bin/magento module:enable GetJohn_VendorChecker
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Usage

### Check All Installed Packages

```bash
composer vendor:check
```

This will scan your `composer.lock` file and check all known vendor modules for updates.

**Note:** Only packages from supported vendors (Amasty, Mageplaza, BSS Commerce, Aheadworks) will be checked. Use `-v` flag to see which vendors are supported.

```bash
composer vendor:check -v
```

### Check Specific Packages

```bash
composer vendor:check --packages=amasty/promo,mageplaza/layered-navigation-m2-pro
```

### Check a Single Vendor URL

```bash
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html
```

### Compare Across Multiple Sources

```bash
composer vendor:check --compare-sources --packages=amasty/promo
```

This will show you:
- Version from `composer show`
- Version from Magento Marketplace
- Version from vendor's website
- Whether all three match

### Verbose Output with Changelog

```bash
composer vendor:check -v
```

Shows recent changelog entries for each module.

### JSON Output (for CI/CD)

```bash
composer vendor:check --json
```

Outputs results in JSON format for parsing in automated scripts.

### Custom composer.lock Path

```bash
composer vendor:check --path=/path/to/composer.lock
```

## Command Options

| Option | Short | Description |
|--------|-------|-------------|
| `--path` | `-p` | Path to composer.lock file (default: ./composer.lock) |
| `--packages` | - | Comma-separated list of packages to check |
| `--url` | `-u` | Single vendor URL to check |
| `--verbose` | `-v` | Show detailed output including changelog |
| `--compare-sources` | `-c` | Compare versions across Composer, Marketplace, and vendor sites |
| `--json` | `-j` | Output results as JSON |

## Example Output

```
╔════════════════════════════════════════════════════════════════════════════╗
║                        Vendor Version Check Report                         ║
╚════════════════════════════════════════════════════════════════════════════╝

  ✓  amasty/module-admin-actions-log
      Installed: 2.1.0              Latest: 2.1.0

  ↑  amasty/promo
      Installed: 2.22.0             Latest: 2.23.1
      Recent changes:
        • 2.23.1 - Sep 15, 2024
        • 2.23.0 - Aug 22, 2024

  ✓  mageplaza/layered-navigation-m2-pro
      Installed: 4.5.13             Latest: 4.5.13

─────────────────────────────────────────────────────────────────────────────
Summary: 2 up-to-date, 1 updates available, 0 errors
```

## Supported Vendors

The module currently has built-in support for:

- **Amasty** (amasty.com)
- **Mageplaza** (mageplaza.com)
- **BSS Commerce** (bsscommerce.com)
- **Aheadworks** (aheadworks.com)
- **MageMe** (mageme.com)
- **Mageworx** (mageworx.com)
- **XTENTO** (xtento.com)

## Adding Custom Package URLs

If you have modules from other vendors or custom URLs, you can add them programmatically:

```php
<?php
use GetJohn\VendorChecker\Service\ComposerIntegration;

$integration = new ComposerIntegration('./composer.lock');
$integration->addPackageUrlMapping(
    'vendor/module-name',
    'https://vendor.com/product-page.html'
);
```

Or add them directly to the `$packageUrlMappings` array in:
`src/Service/ComposerIntegration.php`

## How It Works

1. **Reads composer.lock** to get your installed packages
2. **Checks known vendor URLs** for each package
3. **Scrapes vendor websites** using intelligent pattern matching
4. **Parses changelog sections** to extract version history
5. **Compares versions** across all sources
6. **Generates reports** with clear status indicators

## Use Cases

### Daily Development
```bash
# Quick check before starting work
composer vendor:check
```

### Pre-Upgrade Planning
```bash
# See what's changed in verbose mode
composer vendor:check -v --packages=amasty/promo
```

### CI/CD Pipeline
```bash
# Check for updates and fail build if critical updates exist
composer vendor:check --json | jq '.[] | select(.status=="UPDATE_AVAILABLE")'
```

### Multi-Source Validation
```bash
# Ensure all sources are in sync before deploying
composer vendor:check --compare-sources
```

## Requirements

- PHP 7.4 or higher
- Magento 2.4.x or higher
- Composer 2.x
- ext-dom
- ext-json

## Technical Architecture

This module implements a Composer Plugin using the `composer-plugin-api`. The architecture consists of:

- **ComposerPlugin.php** - Registers the plugin with Composer
- **CommandProvider.php** - Provides the custom command
- **VendorCheckCommand.php** - CLI command with all options
- **VersionChecker.php** - Core scraping and version detection logic
- **ComposerIntegration.php** - Integration with composer.lock and package management

## Troubleshooting

### Command not found after installation

```bash
# Rebuild Composer autoload
composer dump-autoload
```

### Package URL not found

Check if the package has a URL mapping in `ComposerIntegration.php`. You can add custom mappings using `addPackageUrlMapping()`.

### Vendor website changed structure

The module uses pattern matching for each vendor. If a vendor changes their website structure, you may need to update the patterns in `VersionChecker.php` under the `$vendorPatterns` array.

## Contributing

Found a vendor that's not supported? Want to improve the pattern matching? Contributions are welcome!

1. Fork the repository
2. Create a feature branch
3. Add your changes
4. Submit a pull request

## License

MIT License - see LICENSE file for details

## Author

**John @ GetJohn**  
https://getjohn.co.uk

## Support

For issues, questions, or feature requests, please open an issue on the GitHub repository.

---

**Note**: This tool scrapes vendor websites and is subject to changes in their HTML structure. While we maintain patterns for major vendors, occasional updates may be needed if vendors redesign their sites.
