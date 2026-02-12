# Changelog

All notable changes to the Vendor Version Checker module are documented here.

## v2.0.0 — 2026-02-12

### Breaking Changes

- **Removed Magento dependency** — No longer requires `magento/framework` or `ext-dom`. Works on any Composer project.
- **Removed `generateReport()` from ComposerIntegration** — Report formatting moved to `OutputFormatter`.
- **Removed `getSupportedVendors()`**, `addPackageUrlMapping()`, `getPackageUrlMappings()` from ComposerIntegration.
- **Removed `--compare-sources` option** — Was defined but never implemented.
- **Changed `checkForUpdates()` signature** — Now accepts optional `ProgressReporter` instead of `$verbose` boolean.
- **`compareVersions()` is now `public static`** — Accessible without instantiation.
- **Constructor signature changed** — `ComposerIntegration` now accepts `$configPath`, `$cache`, and `$versionChecker` parameters.

### New Features

- **Auto-discovery** — All packages from `composer.lock` are checked via Packagist by default. No hardcoded package lists needed.
- **PackageResolver** — New class determines check strategy per package (skip/website/private_repo/packagist) with configurable skip lists.
- **ResultCache** — File-based result caching with configurable TTL. Avoids redundant HTTP calls on repeated runs.
- **ProgressReporter** — Per-package progress display: `[12/85] amasty/promo ... packagist OK`.
- **OutputFormatter** — Formats results as table, JSON, or CSV with file write support.
- **`--format` option** — Choose output format: `table` (default), `json`, `csv`.
- **`--output` option** — Write formatted results directly to a file.
- **`--no-cache` option** — Skip reading cached results.
- **`--clear-cache` option** — Clear cache before running.
- **`--cache-ttl` option** — Custom cache TTL in seconds (default: 3600).
- **`--config` option** — Custom path to packages.php config file.
- **`skip_vendors` config key** — Skip entire vendor prefixes (e.g., magento, laminas, symfony).
- **`skip_packages` config key** — Skip specific packages by name.
- **Packagist pre-fetch for all methods** — Website and private repo checks now pre-fetch Packagist URLs as fallback.

### Improvements

- **Config externalised** — Package URL mappings and skip lists moved from hardcoded arrays to `config/packages.php`.
- **Skip hosts/patterns from config** — `buildPrivateRepoMap()` reads `skip_hosts` and `skip_patterns` from config instead of hardcoded values.
- **68 unit tests** — Full test coverage with Guzzle MockHandler for HTTP mocking. Zero network calls.

### Removed

- `registration.php` — Magento module registration (no longer needed)
- `etc/module.xml` — Magento module XML config (no longer needed)
- `fetch-vendor-modules.sh` — Operational script (not part of plugin)
- Hardcoded `$packageUrlMappings` and `$packagistPackages` arrays from ComposerIntegration
- `--verbose` option redefinition (now uses Symfony's built-in `-v`)

---

## v0.3.0 — 2026-02-11

### New Features

- **Private Composer repository support** — Auto-detects private repos from `composer.json` repository definitions and authenticates using `auth.json` credentials. Queries private Satis/Composer repos with HTTP basic auth to resolve latest stable versions.
- **Multi-format Satis resolution** — Supports V2 (`p2/`), V1 (`p/`), full `packages.json`, and Satis provider-includes with hash-based URL resolution.
- **Private Repo source badge** — Dashboard and report now display `[via Private Repo]` for versions resolved from private Composer repos.
- **Auth expired detection** — When private repo queries fail, the report shows "auth may be expired" with clear guidance instead of generic error messages.
- **Concurrent HTTP requests** — All URLs are pre-fetched in parallel via Guzzle async promises for significantly faster checks.
- **Hybrid Cloudflare detection** — Header-based detection (cf-ray, cf-mitigated) with body marker fallback for reliable challenge page identification.
- **Exit codes** — `0` = all current, `1` = updates available, `2` = errors (for CI/CD integration).
- **Package filter** — `--packages` option to check specific packages only.

### Improvements

- **Smart repo filtering** — Skips `repo.magento.com`, `marketplace.magento.com`, and internal Satis repos to avoid matching Magento core packages as private repo candidates.
- **Response header caching** — `warmCache()` stores response headers alongside body, enabling header-based Cloudflare detection for cached responses.
- **Auth credential guards** — Validates both username and password exist before building private repo credentials.

### Known Limitations

- **Amasty** — Private repo (`composer.amasty.com`) returns 403. Global Composer keys deprecated 1 Jan 2026. Requires project-level keys from Amasty customer portal.
- **MageWorx** — Private repo credentials present but returning errors. Auth may be expired.
- **Aheadworks** — Private repo credentials present but repo format not yet verified.

---

## v0.2.0 — 2026-02-11

### Bug Fixes

- **Fixed `getSupportedVendors()` return format** — The method in `ComposerIntegration` was incorrectly transforming vendor keys using `explode('.', $domain)[0]`, causing vendor name mismatches and preventing packages from being matched. Removed the unnecessary transformation so vendor keys are passed through directly from `VersionChecker`.
- **Fixed XTENTO version extraction picking Magento 1 version** — The XTENTO product pages list both M1 and M2 versions. The previous regex matched the first occurrence (M1). Implemented a `version_select: highest` strategy with a case-sensitive pattern that selects the highest (M2) version.

### New Features

- **Packagist API fallback** — When vendor website scraping fails (Cloudflare block, no version on page), the checker now falls back to querying the Packagist API (`repo.packagist.org/p2/`) for the latest stable version. Results from Packagist are clearly labelled `[via Packagist]` in the report.
- **Packagist-only package tracking** — Added support for packages that don't have vendor website patterns but are available on public Packagist. These are checked directly via the Packagist API without requiring website scraping.
- **UNAVAILABLE status** — New status category for packages where the vendor page loads successfully but no version information can be extracted.
- **Cloudflare detection** — HTTP 403 responses containing Cloudflare challenge markers are now detected and reported with a clear "Cloudflare protection detected" message.
- **Version source tracking** — Each result now includes a `source` field indicating whether the version was obtained from `vendor_website` or `packagist`.

### Expanded Package Coverage

Added URL mappings and Packagist tracking for additional packages:

**New vendor website mappings:**
- `amasty/gdpr-cookie`, `amasty/geoipredirect`, `amasty/module-gdpr`, `amasty/number`
- `aheadworks/module-blog`, `mageplaza/module-smtp`

**New Packagist-only packages:**
- `taxjar/module-taxjar`, `webshopapps/module-matrixrate`, `klaviyo/magento2-extension`
- `yotpo/magento2-module-yotpo-loyalty`, `yotpo/module-review`
- `paradoxlabs/authnetcim`, `paradoxlabs/tokenbase`, `justuno.com/m2`
- `stripe/stripe-payments`, `bsscommerce/disable-compare`
- `mageworx/module-donationsmeta`, `mageworx/module-giftcards`

### Technical Improvements

- **Updated User-Agent** — Changed from custom `MagentoVersionChecker/1.0` to a standard Chrome browser User-Agent to reduce bot detection.
- **`section_filter` support in `extractVersion()`** — New vendor pattern option to isolate HTML sections before version extraction.
- **`version_select` support in `extractVersion()`** — When set to `highest`, finds all version matches and returns the highest via `version_compare`.

### Known Limitations

- **Amasty** — All Amasty product pages are protected by Cloudflare Managed Challenge v3. Versions cannot be scraped. Not available on public Packagist.
- **Aheadworks** — Also behind Cloudflare protection. Not available on public Packagist.
- **MageWorx** — Product pages do not display version numbers. Not available on public Packagist.
- **Stripe** — `stripe/stripe-payments` (Magento module) is not published on public Packagist.

---

## v0.1.3 — 2025-11-29

- Allow Guzzle 6.x compatibility.

## v0.1.2 — 2025-11-29

- Fix composer.json configuration.

## v0.1.1 — 2025-11-29

- Module name change.

## v0.1.0 — 2025-11-29

Initial release with vendor website scraping support.
