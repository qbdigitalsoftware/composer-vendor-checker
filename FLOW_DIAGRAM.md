# Command Flow Diagram

## How `composer vendor:check` Works

```
┌──────────────────────────────────────────────────────────────────────────┐
│  User Terminal                                                           │
│  $ composer vendor:check --format=csv --output=report.csv               │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Composer Core                                                           │
│  - Loads installed plugins                                               │
│  - Finds getjohn/module-composer-vendor-checker                          │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  ComposerPlugin.php → CommandProvider.php → VendorCheckCommand.php       │
│                                                                          │
│  1. Parse options (--format, --output, --no-cache, --config, etc.)       │
│  2. Set up ResultCache (unless --no-cache)                               │
│  3. Handle --clear-cache                                                 │
│  4. Create ComposerIntegration with config + cache                       │
│  5. Create ProgressReporter                                              │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  ComposerIntegration.__construct()                                       │
│                                                                          │
│  1. Load config (explicit path or bundled config/packages.php)           │
│  2. Build private repo map from composer.json + auth.json                │
│  3. Create PackageResolver with config + private repo map                │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  getInstalledPackages()                                                  │
│                                                                          │
│  Reads composer.lock → PackageResolver.resolveAll()                      │
│                                                                          │
│  For each package:                                                       │
│    1. skip_packages? → SKIP                                              │
│    2. skip_vendors?  → SKIP                                              │
│    3. URL mapping?   → WEBSITE                                           │
│    4. Private repo?  → PRIVATE_REPO                                      │
│    5. Default        → PACKAGIST                                         │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  checkForUpdates()                                                       │
│                                                                          │
│  Phase 1: Cache check                                                    │
│    - For each non-skipped package: check ResultCache                     │
│    - Cache hit → use cached result, advance progress                     │
│    - Cache miss → add to toCheck list                                    │
│                                                                          │
│  Phase 2: Concurrent pre-fetch                                           │
│    - warmHttpCache() pre-fetches all URLs concurrently via Guzzle async  │
│    - Includes Packagist URLs as fallback for website/private_repo        │
│                                                                          │
│  Phase 3: Check each cache miss                                          │
│    - checkPackage() dispatches to appropriate method                     │
│    - Store result in ResultCache                                         │
│    - Advance ProgressReporter                                            │
│                                                                          │
│  Phase 4: Flush cache to disk                                            │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                    ┌────────────┼────────────┐
                    ▼            ▼            ▼
         ┌──────────────┐ ┌──────────┐ ┌───────────────┐
         │  PACKAGIST   │ │ WEBSITE  │ │ PRIVATE_REPO  │
         │              │ │          │ │               │
         │ Packagist p2 │ │ Scrape   │ │ V2/V1/Satis  │
         │ API check    │ │ vendor   │ │ with auth     │
         │              │ │ page     │ │               │
         │              │ │    ↓     │ │      ↓        │
         │              │ │ Fallback │ │ Fallback      │
         │              │ │ to       │ │ to            │
         │              │ │ Packagist│ │ Packagist     │
         └──────┬───────┘ └────┬─────┘ └──────┬────────┘
                │              │              │
                └──────────────┼──────────────┘
                               ▼
                ┌─────────────────────────────────┐
                │  Results Processing              │
                │                                  │
                │  Each result:                    │
                │  {                               │
                │    package: 'amasty/promo',      │
                │    installed_version: '2.12.0',  │
                │    latest_version: '2.14.0',     │
                │    status: 'UPDATE_AVAILABLE',   │
                │    source: 'packagist'           │
                │  }                               │
                └────────────┬────────────────────┘
                             ▼
                ┌─────────────────────────────────┐
                │  OutputFormatter                 │
                │                                  │
                │  --format=table → Box-drawing    │
                │  --format=json  → JSON           │
                │  --format=csv   → CSV            │
                │                                  │
                │  --output=file → Write to disk   │
                │  (else)        → Write to stdout │
                └────────────┬────────────────────┘
                             ▼
                ┌─────────────────────────────────┐
                │  Exit Code                       │
                │                                  │
                │  0 = all up to date              │
                │  1 = updates available           │
                │  2 = errors encountered          │
                └─────────────────────────────────┘
```

## Progress Output During Check

```
  [ 1/24] stripe/stripe-payments                          packagist OK
  [ 2/24] klaviyo/magento2-extension                      packagist UPDATE
  [ 3/24] amasty/promo                                    cached UP_TO_DATE
  [ 4/24] xtento/orderexport                              website OK
  [ 5/24] aheadworks/module-blog                          website ERR
  ...
```
