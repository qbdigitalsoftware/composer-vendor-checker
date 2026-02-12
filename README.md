# Magento 2 Vendor Version Checker

A Composer plugin that checks vendor websites, the Packagist API, and private Composer repositories for the latest module versions, helping identify when updates are available that may not yet be reflected in Composer or the Magento Marketplace.

## The Problem This Solves

`composer update` doesn't always show the true latest version of a third-party module. This happens when:

- The vendor hasn't pushed the update to Packagist yet
- The version available through Composer repositories lags behind the vendor's latest release
- The module is distributed via a private Composer repository with restricted access

This tool checks **vendor websites**, the **Packagist API**, and **private Composer repositories** (with auth.json credentials) to give you the complete picture.

## Features

- Custom Composer command: `composer vendor:check`
- Checks all installed third-party modules at once
- Three version sources: vendor website scraping, Packagist API, and private Composer repos
- Auto-detects private repos from composer.json and authenticates via auth.json
- Packagist-only tracking for packages without vendor website patterns
- Cloudflare bot protection detection with clear error messaging
- Intelligent version selection (`version_select: highest`) for pages with multiple versions
- JSON output for CI/CD pipelines
- HTML dashboard for visual reporting (TailwindCSS + Alpine.js)
- UNAVAILABLE status for packages that can't be checked automatically

## Supported Vendors

### Website Scraping

| Vendor | Status |
|--------|--------|
| Amasty | Cloudflare blocked |
| Aheadworks | Cloudflare blocked |
| BSS Commerce | Active |
| MageMe | Active |
| Mageplaza | Active |
| MageWorx | No version on page |
| XTENTO | Active |

### Private Composer Repositories

| Vendor | Repository | Status |
|--------|-----------|--------|
| Amasty | composer.amasty.com | Auth expired (needs project-level keys) |
| MageWorx | packages.mageworx.com | Auth expired |
| XTENTO | repo.xtento.com | Working |
| Aheadworks | dist.aheadworks.com | Credentials present |
| BSS Commerce | composer.bsscommerce.com | Credentials present |

### Packagist API

Justuno, Klaviyo, ParadoxLabs, Stripe, TaxJar, WebShopApps, Yotpo, and any package published to public Packagist.

## Installation

### Via Composer

```bash
composer require getjohn/magento2-vendor-checker
```

### Manual Installation

1. Clone or download this repository
2. Place in your project or as a path repository
3. Require via Composer:

```bash
composer config repositories.vendor-checker path /path/to/module
composer require getjohn/magento2-vendor-checker
```

## Usage

### Check All Installed Packages

```bash
composer vendor:check
```

Scans `composer.lock` and checks all known vendor modules for updates. Packages are checked via their vendor website first, with Packagist API as a fallback.

### Check Specific Packages

```bash
composer vendor:check --packages=amasty/promo,mageplaza/module-smtp
```

### Check a Single Vendor URL

```bash
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html
```

### Verbose Output with Changelog

```bash
composer vendor:check -v
```

### JSON Output (for CI/CD)

```bash
composer vendor:check --json
```

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
Vendor Version Check Report
==============================================================================

  UP_TO_DATE   yotpo/module-review
               Installed: 3.3.0    Latest: 3.3.0 [via Packagist]

  UPDATE       xtento/orderexport
               Installed: 2.16.3   Latest: 2.17.6 [via Website]

  UPDATE       klaviyo/magento2-extension
               Installed: 4.0.9    Latest: 4.4.2 [via Packagist]

  ERROR        amasty/promo
               Cloudflare protection detected — website requires browser verification

  UNAVAILABLE  stripe/stripe-payments
               Package not found on Packagist

------------------------------------------------------------------------------
Summary: 1 up-to-date, 10 updates available, 7 errors, 4 unavailable
```

## HTML Dashboard

The module generates an HTML dashboard at `docs/index.html` for visual reporting:

- Summary cards showing total/up-to-date/updates/unavailable/errors
- Filterable results table with status indicators
- Version source badges (Website / Packagist / Failed)
- Module documentation and architecture reference
- Works offline — data is inlined directly in the HTML (no CORS issues)

To regenerate the dashboard after a scan, the runner script updates the inline data automatically.

## How It Works

1. **Reads composer.lock, composer.json and auth.json** to identify installed packages, private repo URLs and credentials
2. **Matches packages** against known vendor URL mappings, the Packagist registry, and private Composer repositories
3. **Scrapes vendor websites** using vendor-specific regex patterns to extract the latest version
4. **Queries private Composer repos** (Satis) with HTTP basic auth for vendors like Amasty, MageWorx and XTENTO
5. **Falls back to Packagist API** when website scraping and private repo checks fail
6. **Compares versions** and generates the report with source tracking

### Version Sources

- **Website** — Directly scrapes the vendor's product page. Most accurate but subject to Cloudflare protection and page structure changes.
- **Private Repo** — Queries the vendor's private Composer repository (Satis) using credentials from auth.json. Supports V2 (`p2/`), V1 (`p/`), full `packages.json`, and Satis provider-includes formats.
- **Packagist** — Queries the public Packagist registry API (`repo.packagist.org/p2/`). Used as fallback and as primary source for packages without vendor website patterns.
- **Failed** — Vendors behind Cloudflare with expired private repo credentials cannot be checked automatically.

## Adding Custom Package URLs

Add URL mappings in `src/Service/ComposerIntegration.php`:

```php
protected $packageUrlMappings = [
    'vendor/module-name' => 'https://vendor.com/product-page.html',
];
```

For Packagist-only packages:

```php
protected $packagistPackages = [
    'vendor/module-name',
];
```

Private Composer repos are auto-detected from `composer.json` repository definitions and authenticated via `auth.json`. No manual configuration needed — just ensure valid credentials are present.

## Technical Architecture

This module implements a Composer Plugin using the `composer-plugin-api ^2.0`:

| File | Purpose |
|------|---------|
| `ComposerPlugin.php` | Registers the plugin with Composer |
| `CommandProvider.php` | Provides the custom CLI command |
| `VendorCheckCommand.php` | CLI command logic and output formatting |
| `VersionChecker.php` | Core scraping engine, vendor patterns, Packagist API, private repo queries |
| `ComposerIntegration.php` | composer.lock + composer.json + auth.json parsing, private repo map, report generation |

## Known Limitations

- **Amasty** — Website blocked by Cloudflare. Private Composer repo (`composer.amasty.com`) returns 403 because global keys were deprecated on 1 Jan 2026. Requires new project-level Composer keys from the Amasty customer portal.
- **Aheadworks** — Website blocked by Cloudflare. Private repo credentials present in auth.json but repo format not yet verified.
- **MageWorx** — Website does not display version numbers. Private repo (`packages.mageworx.com`) credentials present but returning errors — auth may be expired.
- **XTENTO** — Private repo is working and successfully resolving versions via customer-specific XTENTO repository.
- **Stripe Payments** — `stripe/stripe-payments` is distributed via Stripe's private Composer repository, not public Packagist.
- **Website structure changes** — Vendor-specific regex patterns may need updating if vendors redesign their product pages.

## Requirements

- PHP 7.4 or higher
- Magento 2.4.x or higher
- Composer 2.x
- ext-json
- guzzlehttp/guzzle (included in Magento)

## Troubleshooting

### Command not found

```bash
composer dump-autoload
```

### Package URL not found

Check `$packageUrlMappings` in `ComposerIntegration.php` or add the package to `$packagistPackages`.

### Vendor website changed structure

Update the regex patterns in `VersionChecker.php` under the `$vendorPatterns` array.

### Cloudflare blocked

Vendors using Cloudflare Managed Challenge cannot be scraped. If the package is on Packagist, the tool will fall back automatically. Otherwise it will report as ERROR with a clear message.

## Author

**John @ GetJohn**
https://getjohn.co.uk

## License

MIT License - see LICENSE file for details
