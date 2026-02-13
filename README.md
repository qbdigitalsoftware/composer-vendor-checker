# Composer Vendor Version Checker

A Composer plugin that checks all installed packages for available updates. Works on **any Composer project** with zero configuration — auto-discovers packages via Packagist, with optional support for vendor website scraping and private Composer repositories.

## The Problem This Solves

`composer update` doesn't always show the true latest version of a third-party package. This happens when:

- The vendor hasn't pushed the update to Packagist yet
- The version available through Composer repositories lags behind the vendor's latest release
- The package is distributed via a private Composer repository with restricted access

This tool checks **Packagist** (auto-discovery), **private Composer repos** (with auth.json credentials), and **vendor websites** to give you the complete picture.

## Features

- Custom Composer command: `composer vendor:check`
- **Auto-discovery** — checks all installed packages via Packagist with zero config
- Three version sources: Packagist API, private Composer repos, vendor website scraping
- **Result caching** — avoids redundant HTTP calls within a configurable TTL
- **Per-package progress** — live progress indicator during checks
- **Multiple output formats** — table, JSON, CSV
- **File output** — write results directly to a file
- Auto-detects private repos from composer.json and authenticates via auth.json
- Configurable skip lists — exclude vendors or packages from checks
- Cloudflare bot protection detection with clear error messaging
- Exit codes for CI/CD: `0` = all current, `1` = updates available, `2` = errors
- Concurrent HTTP pre-fetching via Guzzle async for fast checks

## Installation

### Via Composer (Recommended)

```bash
composer require --dev getjohn/module-composer-vendor-checker
```

### As a Path Repository

```bash
composer config repositories.vendor-checker path /path/to/module-composer-vendor-checker
composer require --dev getjohn/module-composer-vendor-checker
```

### Verify Installation

```bash
composer vendor:check --help
```

## Usage

### Check All Installed Packages

```bash
composer vendor:check
```

Scans `composer.lock` and checks every non-skipped package for updates. Packages are checked via Packagist by default, with private repo and website overrides configurable via `config/packages.php`.

### Check Specific Packages

```bash
composer vendor:check --packages=stripe/stripe-payments,amasty/promo
```

### Check a Single Vendor URL

```bash
composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html
```

### Output Formats

```bash
# Table format (default)
composer vendor:check

# JSON output
composer vendor:check --format=json

# CSV output
composer vendor:check --format=csv

# Legacy JSON alias
composer vendor:check --json
```

### Write to File

```bash
composer vendor:check --format=csv --output=report.csv
composer vendor:check --format=json --output=versions.json
```

### Caching

```bash
# Skip cached results (force fresh check)
composer vendor:check --no-cache

# Clear cache before running
composer vendor:check --clear-cache

# Custom TTL (seconds)
composer vendor:check --cache-ttl=7200
```

### Verbose Output

```bash
composer vendor:check -v
```

### Custom Lock Path

```bash
composer vendor:check --path=/path/to/project/composer.lock
```

### Custom Config

```bash
composer vendor:check --config=/path/to/packages.php
```

## Command Options

| Option | Short | Description |
|--------|-------|-------------|
| `--path` | `-p` | Path to composer.lock file (default: ./composer.lock) |
| `--packages` | - | Comma-separated list of packages to check |
| `--url` | `-u` | Single vendor URL to check |
| `--format` | `-f` | Output format: table, json, csv (default: table) |
| `--output` | `-o` | Write results to file path |
| `--json` | `-j` | Alias for --format=json |
| `--no-cache` | - | Skip reading cached results |
| `--clear-cache` | - | Clear cache before running |
| `--cache-ttl` | - | Cache TTL in seconds (default: 3600) |
| `--config` | `-c` | Path to packages.php config file |
| `--verbose` | `-v` | Show detailed output |

## Example Output

```text
Checking packages from: ./composer.lock

  [ 1/24] stripe/stripe-payments                          packagist OK
  [ 2/24] klaviyo/magento2-extension                      packagist UPDATE
  [ 3/24] xtento/orderexport                              website OK
  ...

  Vendor Version Check Report
  --------------------------------------------------------------------------

  ✓  stripe/stripe-payments
      Installed: 3.5.0              Latest: 3.5.0 [via Packagist]

  ↑  klaviyo/magento2-extension
      Installed: 4.4.2              Latest: 4.5.0 [via Packagist]

  ✗  amasty/promo
      Installed: 2.12.0             Latest: Error
      Error: Cloudflare protection detected — website requires browser verification

  --------------------------------------------------------------------------
  Summary: 15 up-to-date, 6 updates available, 0 unavailable, 3 errors
```

## Configuration

Configuration is optional. Without a config file, all packages are checked via Packagist.

### Config File Format

The plugin reads `config/packages.php` (bundled) or a custom path via `--config`. See `config/packages.php.example` for the full format.

Key configuration options:

```php
return [
    // Website URL overrides — checked before Packagist
    'package_url_mappings' => [
        'amasty/promo' => 'https://amasty.com/special-promotions-for-magento-2.html',
    ],

    // Vendor prefixes to skip entirely
    'skip_vendors' => [
        'magento', 'laminas', 'symfony', 'monolog', 'psr',
        'phpunit', 'guzzlehttp', 'doctrine',
    ],

    // Specific packages to skip
    'skip_packages' => [
        'getjohn/module-customsprice',
    ],

    // Private repo hosts to skip
    'skip_hosts' => [
        'repo.magento.com',
    ],

    // Host patterns to skip (regex)
    'skip_patterns' => [
        '/\.satis\./i',
    ],
];
```

### Package Resolution Order

For each package in `composer.lock`:

1. **Skip** — if vendor is in `skip_vendors` or package is in `skip_packages`
2. **Website** — if package has a URL in `package_url_mappings` (with Packagist fallback)
3. **Private Repo** — if package came from a private Composer repo detected in `composer.json` + `auth.json` (with Packagist fallback)
4. **Packagist** — default auto-discovery for everything else

## How It Works

1. **Reads composer.lock** to get all installed packages
2. **Resolves check strategy** for each package via `PackageResolver`
3. **Checks cache** — uses cached results within TTL if available
4. **Pre-fetches URLs** concurrently via Guzzle async for cache misses
5. **Checks each package** using its resolved method (Packagist, private repo, or website)
6. **Stores results** in the result cache
7. **Formats output** via `OutputFormatter` (table, JSON, or CSV)

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

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | All packages up to date |
| 1 | Updates available |
| 2 | Errors encountered |

## Requirements

- PHP 7.4 or higher
- Composer 2.x
- ext-json
- guzzlehttp/guzzle ^6.5 or ^7.0

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

## Author

**John @ GetJohn**
https://getjohn.co.uk

## License

MIT License - see LICENSE file for details
