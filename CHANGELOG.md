# Changelog - Vendor Filtering Update

## Version 1.2.0 - New Vendors Added (MageMe, Mageworx, XTENTO)

### What's New

Added support for three additional major Magento extension vendors, bringing the total from 4 to 7 supported vendors.

### New Vendors

**MageMe (mageme.com)**
- Composer package: `mageme/module-webforms-3`
- Example module: WebForms Pro 3 - Form Builder
- Specialty: Custom forms, form builders, data collection
- URL: https://mageme.com/magento-2-form-builder.html

**Mageworx (mageworx.com)**
- Composer package: `mageworx/module-giftcards`
- Example module: Gift Cards
- Specialty: Gift cards, fees, shipping, marketing
- URL: https://www.mageworx.com/magento-2-gift-cards.html

**XTENTO (xtento.com)**
- Composer package: `xtento/orderexport`
- Example module: Order Export
- Specialty: Import/export solutions, order management
- URL: https://www.xtento.com/magento-extensions/magento-order-export-module.html

### Technical Changes

**VersionChecker.php:**
- Added scraping patterns for MageMe (changelog format: "3.5.0 Sep 17 2025")
- Added scraping patterns for Mageworx (changelog format: "Version: X.X.X (Date)")
- Added scraping patterns for XTENTO (changelog format: "===== X.X.X =====")

**ComposerIntegration.php:**
- Added package URL mappings for all three vendors
- Supports both MageMe v2 (`mageme/module-webforms`) and v3 (`mageme/module-webforms-3`) packages

**Documentation:**
- Updated README.md with new vendor list
- Updated command help text
- Created NEW_VENDORS_v1.2.0.md with detailed vendor information

### Testing

```bash
# Test new vendors
composer vendor:check --packages=mageme/module-webforms-3,mageworx/module-giftcards,xtento/orderexport -v

# Verify all 7 vendors are supported
composer vendor:check -v
# Output should show: amasty, mageplaza, bsscommerce, aheadworks, mageme, mageworx, xtento
```

### Benefits

- 75% increase in vendor coverage (from 4 to 7 vendors)
- Support for popular extension categories: forms, gift cards, import/export
- Better coverage of enterprise Magento ecosystem

---

## Version 1.1.0 - Intelligent Vendor Filtering

### What Changed

The "check all packages" functionality now **automatically filters** to only include vendors for which we have defined scraping patterns. This prevents errors and unnecessary HTTP requests for vendors we don't support.

### Key Changes

#### 1. Automatic Vendor Filtering

**Before:**
- Would attempt to check ALL packages in composer.lock
- Would fail or skip packages from unsupported vendors
- Could generate many error messages

**After:**
- Only checks packages from supported vendors (Amasty, Mageplaza, BSS Commerce, Aheadworks)
- Silently skips unsupported vendors
- Cleaner output with no unnecessary errors

#### 2. New Methods Added

**VersionChecker.php:**
```php
// Get list of supported vendor domains (e.g., ['amasty.com', 'mageplaza.com', ...])
public function getSupportedVendors()

// Extract vendor name from package name (e.g., 'amasty' from 'amasty/promo')
public function getVendorFromPackage($packageName)
```

**ComposerIntegration.php:**
```php
// Get list of supported vendor names (e.g., ['amasty', 'mageplaza', ...])
public function getSupportedVendors()
```

#### 3. Enhanced Command Output

When running with verbose flag (`-v`), the command now shows which vendors are supported:

```bash
$ composer vendor:check -v

Checking all installed packages from: ./composer.lock
Supported vendors: amasty, mageplaza, bsscommerce, aheadworks
Note: Only packages from these vendors will be checked
```

#### 4. Updated Documentation

- README.md - Added note about vendor filtering
- PROJECT_SUMMARY.md - Added "Smart Package Filtering" feature
- Command help text - Clarified behavior

### Technical Implementation

The filtering works by:

1. **Reading vendor patterns** from `VersionChecker::$vendorPatterns` array
2. **Extracting vendor domains** (e.g., 'amasty.com', 'mageplaza.com')
3. **Converting to vendor names** (e.g., 'amasty', 'mageplaza')
4. **Filtering composer.lock packages** to only include these vendors
5. **Additionally filtering** by URL mappings (must have a known URL)

### Benefits

✅ **Cleaner output** - No errors from unsupported vendors
✅ **Faster execution** - Fewer HTTP requests
✅ **Better UX** - Users understand what's being checked
✅ **Maintainable** - Adding new vendor support is centralized
✅ **Self-documenting** - List of supported vendors is derived from patterns

### Backward Compatibility

This change is **fully backward compatible**:

- Existing commands continue to work
- Command options unchanged
- JSON output format unchanged
- Programmatic API unchanged

The only difference is that fewer packages are checked (only supported vendors), which is the intended improvement.

### Example Output

#### Before Update
```bash
$ composer vendor:check

Checking all installed packages from: ./composer.lock

  ✓  amasty/promo
      Installed: 2.22.0              Latest: 2.22.0

  ✗  somevendor/module
      Error: No pattern defined for vendor

  ✗  othervendor/extension
      Error: No pattern defined for vendor

─────────────────────────────────────────────────────────
Summary: 1 up-to-date, 0 updates available, 2 errors
```

#### After Update
```bash
$ composer vendor:check

Checking all installed packages from: ./composer.lock

  ✓  amasty/promo
      Installed: 2.22.0              Latest: 2.22.0

─────────────────────────────────────────────────────────
Summary: 1 up-to-date, 0 updates available, 0 errors
```

Much cleaner! Unsupported vendors are simply not checked.

#### With Verbose Flag
```bash
$ composer vendor:check -v

Checking all installed packages from: ./composer.lock
Supported vendors: amasty, mageplaza, bsscommerce, aheadworks
Note: Only packages from these vendors will be checked

  ✓  amasty/promo
      Installed: 2.22.0              Latest: 2.22.0
      Recent changes:
        • 2.22.0 - Aug 15, 2024

─────────────────────────────────────────────────────────
Summary: 1 up-to-date, 0 updates available, 0 errors
```

### How to Add Support for New Vendors

To add a new vendor, you need to update TWO places:

#### 1. Add Scraping Pattern (VersionChecker.php)

```php
private $vendorPatterns = [
    // ... existing patterns
    
    'newvendor.com' => [
        'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
        'changelog_pattern' => '/##\s*v?(\d+\.\d+\.\d+)\s*\(([^)]+)\)/i',
    ]
];
```

#### 2. Add Package URL Mappings (ComposerIntegration.php)

```php
private $packageUrlMappings = [
    // ... existing mappings
    
    'newvendor/package-name' => 'https://newvendor.com/product-page.html',
];
```

That's it! The vendor will automatically be:
- Included in the supported vendors list
- Checked when running `composer vendor:check`
- Shown in verbose output

### Migration Notes

**No action required** - This is an enhancement that improves existing behavior without breaking changes.

If you were working around errors from unsupported vendors, you can now remove those workarounds.

### Testing Recommendations

```bash
# Test basic functionality
composer vendor:check

# Test verbose output (should show supported vendors)
composer vendor:check -v

# Test JSON output (structure unchanged)
composer vendor:check --json | jq .

# Test specific package (still works)
composer vendor:check --packages=amasty/promo

# Test unsupported vendor (should be silently skipped)
# Add a test package from unsupported vendor to composer.lock
# Run: composer vendor:check
# Result: Package should not appear in output
```

---

## Files Modified

1. **src/Service/VersionChecker.php**
   - Added `getSupportedVendors()` method
   - Added `getVendorFromPackage()` method

2. **src/Service/ComposerIntegration.php**
   - Modified `getInstalledPackages()` to filter by supported vendors
   - Added `getSupportedVendors()` method

3. **src/Command/VendorCheckCommand.php**
   - Updated `checkAllPackages()` to show supported vendors in verbose mode
   - Updated help text to clarify filtering behavior

4. **README.md**
   - Added note about automatic vendor filtering
   - Updated usage examples

5. **PROJECT_SUMMARY.md**
   - Added "Smart Package Filtering" to key features

---

**Version**: 1.1.0  
**Date**: November 23, 2024  
**Author**: John @ GetJohn
