# Directory Structure

```
module-composer-vendor-checker/
│
├── composer.json                          # Composer configuration (type: composer-plugin)
├── phpunit.xml                            # PHPUnit test configuration
├── .gitignore                             # Git ignore rules
├── LICENSE                                # MIT License
├── README.md                              # Full documentation
├── INSTALL.md                             # Installation guide
├── CHANGELOG.md                           # Version history
├── STRUCTURE.md                           # This file
├── FLOW_DIAGRAM.md                        # Command flow diagram
│
├── config/
│   ├── packages.php                       # Package configuration (skip lists, URL mappings)
│   └── packages.php.example              # Example configuration template
│
├── src/
│   ├── ComposerPlugin.php                # Main plugin class (implements PluginInterface)
│   ├── CommandProvider.php                # Provides commands to Composer
│   │
│   ├── Command/
│   │   └── VendorCheckCommand.php        # CLI command (composer vendor:check)
│   │
│   ├── Service/
│   │   ├── ComposerIntegration.php       # Core orchestration: lock parsing, check dispatch, caching
│   │   ├── VersionChecker.php            # HTTP client, Packagist API, private repo, website scraping
│   │   ├── PackageResolver.php           # Determines check strategy per package
│   │   └── ResultCache.php               # File-based result caching with TTL
│   │
│   └── Output/
│       ├── OutputFormatter.php           # Table, JSON, CSV formatting
│       └── ProgressReporter.php          # Per-package progress display
│
├── tests/
│   ├── Unit/
│   │   ├── Service/
│   │   │   ├── PackageResolverTest.php   # Resolution strategy tests
│   │   │   ├── VersionCheckerTest.php    # HTTP mocking, Packagist/private repo tests
│   │   │   ├── ComposerIntegrationTest.php # Integration with fixtures
│   │   │   └── ResultCacheTest.php       # Cache TTL, persistence tests
│   │   ├── Output/
│   │   │   └── OutputFormatterTest.php   # Table/JSON/CSV format tests
│   │   └── Command/
│   │       └── VendorCheckCommandTest.php # Command options and exit codes
│   │
│   └── Fixtures/
│       ├── composer.lock                 # Minimal lock file with 7 test packages
│       ├── composer.json                 # Test repos (amasty, xtento, magento, satis)
│       ├── auth.json                     # Test HTTP basic credentials
│       ├── packages.php                  # Test config with skip lists
│       └── packagist-response.json       # Sample Packagist p2 API response
│
├── examples/
│   └── custom-config.php                 # Programmatic usage example
│
└── docs/
    └── ...                               # HTML delivery reports
```

## Key Files Explained

### src/

- **ComposerPlugin.php** — Entry point for Composer plugin system. Implements `PluginInterface` and `Capable`, returns `CommandProvider` capability.

- **CommandProvider.php** — Registers custom commands. Returns `[new VendorCheckCommand()]`.

### src/Command/

- **VendorCheckCommand.php** — The CLI command `composer vendor:check`. Handles options (`--format`, `--output`, `--no-cache`, `--clear-cache`, `--cache-ttl`, `--config`, etc.), sets up services, and coordinates the check flow.

### src/Service/

- **PackageResolver.php** — Determines how to check each package. Resolution: skip lists -> website overrides -> private repo -> Packagist (default).

- **ComposerIntegration.php** — Core orchestrator. Reads composer.lock, builds private repo map from composer.json + auth.json, loads config, dispatches checks via VersionChecker with cache and progress support.

- **VersionChecker.php** — HTTP client with async pre-fetching. Queries Packagist p2 API, private Composer repos (V2/V1/Satis), and scrapes vendor websites with vendor-specific regex patterns.

- **ResultCache.php** — Single JSON file cache with configurable TTL. Lazy directory creation, dirty tracking, and explicit flush for batched writes.

### src/Output/

- **OutputFormatter.php** — Formats results as table (box-drawing report), JSON, or CSV. Handles file writing.

- **ProgressReporter.php** — Per-package console progress: `[12/85] amasty/promo ... packagist OK`.

### config/

- **packages.php** — Package configuration. Defines `package_url_mappings`, `skip_vendors`, `skip_packages`, `skip_hosts`, `skip_patterns`. Optional — everything defaults to Packagist without config.

## How It All Works Together

1. **Composer loads the plugin** (ComposerPlugin.php)
2. **Plugin registers commands** (via CommandProvider.php)
3. **User runs `composer vendor:check`**
4. **VendorCheckCommand** parses options, sets up cache, creates ComposerIntegration
5. **ComposerIntegration** loads config, builds private repo map, creates PackageResolver
6. **PackageResolver** determines check strategy for each package in composer.lock
7. **ResultCache** returns cached results for packages checked within TTL
8. **VersionChecker** pre-fetches URLs concurrently, then checks each cache miss
9. **ProgressReporter** shows live per-package progress
10. **OutputFormatter** renders results in the requested format
