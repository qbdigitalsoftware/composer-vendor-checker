# Changelog

All notable changes to the Vendor Version Checker module are documented here.

## v1.0.0 — 2026-04-13

First public Packagist release. Plugin is available as `qbdigitalsoftware/composer-vendor-checker`.

### Rebranding

- **Namespace change**: `GetJohn\VendorChecker` → `QBDigital\VendorChecker`. Users of pre-1.0 versions must update `use` statements and `extra.class` references.
- **Package name change**: published on Packagist as `qbdigitalsoftware/composer-vendor-checker`.
- **Author**: Carl Simpson / QB Digital Software Ltd.
- **License**: MIT.

### Bug Fixes

- **Fixed `getSupportedVendors()` return format** — The method in `ComposerIntegration` was incorrectly transforming vendor keys using `explode('.', $domain)[0]`, causing vendor name mismatches and preventing packages from being matched. Removed the unnecessary transformation so vendor keys are passed through directly from `VersionChecker`.
- **Fixed XTENTO version extraction picking Magento 1 version** — The XTENTO product pages list both M1 and M2 versions. The previous regex matched the first occurrence (M1). Implemented a `version_select: highest` strategy with a case-sensitive pattern that selects the highest (M2) version.

### New Features

- **Explicit Packagist package list** — Packagist is only queried for packages explicitly listed in `packagist_packages` config (Stripe, Klaviyo, Yotpo, etc.). Unknown packages resolve as UNRESOLVED instead of silently hitting Packagist.
- **UNRESOLVED status** — New status for packages with no configured check method. Displayed as `·` in table output.
- **UNAVAILABLE status** — New status for packages where the vendor page loads but no version can be extracted.
- **Cloudflare detection** — HTTP 403 responses containing Cloudflare Managed Challenge markers detected and reported clearly.
- **Version source tracking** — Each result includes a `source` field (Website, Packagist, or Private Repo).
- **Private Composer repository support** — Auto-detects private repos from `composer.json` and authenticates using `auth.json` credentials. Supports V2 (`p2/`), V1 (`p/`), full `packages.json`, and Satis provider-includes with hash-based URL resolution.
- **Auth expired detection** — When private repo queries fail, reports "auth may be expired" with actionable guidance.
- **Concurrent HTTP requests** — All URLs pre-fetched in parallel via Guzzle async promises for significantly faster checks.
- **Hybrid Cloudflare detection** — Header-based detection (cf-ray, cf-mitigated) with body marker fallback.
- **Exit codes** — `0` = all current, `1` = updates available, `2` = errors/unresolved (for CI/CD integration).
- **Package filter** — `--packages` option to check specific packages only.

### Expanded Package Coverage

Package coverage expanded from ~10 to 29 tracked packages.

**New vendor website mappings:**
- `aheadworks/module-blog`, `mageplaza/module-smtp`

**New private repo resolutions (via composer.amasty.com):**
- `amasty/gdpr-cookie`, `amasty/geoipredirect`, `amasty/module-gdpr`, `amasty/number`
- All Amasty packages now resolve via `composer.amasty.com` private repo (auto-detected from composer.json + auth.json)

**New Packagist-only packages:**
- `taxjar/module-taxjar`, `webshopapps/module-matrixrate`, `klaviyo/magento2-extension`
- `yotpo/magento2-module-yotpo-loyalty`, `yotpo/module-review`
- `paradoxlabs/authnetcim`, `paradoxlabs/tokenbase`, `justuno.com/m2`
- `stripe/stripe-payments`, `bsscommerce/disable-compare`
- `mageworx/module-donationsmeta`, `mageworx/module-giftcards`

### Improvements

- **Smart repo filtering** — Skips `repo.magento.com`, `marketplace.magento.com`, and internal Satis repos.
- **Response header caching** — `warmCache()` stores response headers alongside body for cached Cloudflare detection.
- **Selective Packagist pre-fetching** — `warmHttpCache()` only pre-fetches Packagist URLs for packages resolved via the packagist method, reducing unnecessary HTTP calls.
- **Updated User-Agent** — Standard Chrome User-Agent to reduce bot detection.
- **`section_filter` and `version_select` in `extractVersion()`** — New vendor pattern options for precise version extraction.

### Removed

- Dead methods: `checkMultiplePackages`, `getComposerVersion` (shell_exec security risk), `getMarketplaceVersion`, `getMarketplaceUrl`
- Packagist fallback from website and private repo check methods — each source now stands alone

### Documentation

- Complete README rewrite covering three version sources, installation, usage, architecture, troubleshooting
- CHANGELOG with full change history
- Interactive HTML dashboard (TailwindCSS + Alpine.js) with embedded JSON data

### Known Limitations

- **Amasty** — Resolved via `composer.amasty.com` private repo. Requires valid project-level keys (global keys deprecated 1 Jan 2026).
- **MageWorx** — Private repo credentials returning errors. Auth likely expired.
- **Aheadworks** — Cloudflare-protected website; private repo credentials present but repo format not yet verified.

---

## v0.1.3 — 2025-11-29

- Allow Guzzle 6.x compatibility.

## v0.1.2 — 2025-11-29

- Fix composer.json configuration.

## v0.1.1 — 2025-11-29

- Module name change.

## v0.1.0 — 2025-11-29

Initial release with vendor website scraping support.
