# New Vendors Added - Version 1.2.0

## Summary

Three new Magento 2 extension vendors have been added to the version checker with full support for scraping patterns and Magento Marketplace integration.

## New Vendors

### 1. MageMe (mageme.com)

**Composer Package**: `mageme/module-webforms-3`  
**Example Module**: WebForms Pro 3 - Form Builder  
**Product URL**: https://mageme.com/magento-2-form-builder.html  
**Marketplace**: Available on Adobe Commerce Marketplace

**Version Pattern**: 
- Matches standard version numbers (e.g., "3.5.0")
- Found throughout product pages and changelog

**Changelog Pattern**:
- Format: `3.5.0 Sep 17 2025`
- Well-structured changelog with clear version headers
- Includes detailed feature lists and fixes

**Example Version**: 3.5.0 (Sep 17, 2025)

---

### 2. Mageworx (mageworx.com)

**Composer Package**: `mageworx/module-giftcards`  
**Example Module**: Gift Cards  
**Product URL**: https://www.mageworx.com/magento-2-gift-cards.html  
**Marketplace**: https://commercemarketplace.adobe.com/mageworx-module-giftcards.html

**Version Pattern**:
- Matches "Version: X.X.X" or "Version X.X.X"
- Consistent across product pages

**Changelog Pattern**:
- Format: `Version: 2.6.0 (April 3, 2019)`
- Clean, dated changelog entries
- Includes features and bug fixes

**Example Version**: 3.0.5 (September 27, 2023)

---

### 3. XTENTO (xtento.com)

**Composer Package**: `xtento/orderexport`  
**Example Module**: Order Export  
**Product URL**: https://www.xtento.com/magento-extensions/magento-order-export-module.html  
**Marketplace**: https://marketplace.magento.com/xtento-orderexport.html

**Version Pattern**:
- Matches standard version format
- Prominently displayed on product pages

**Changelog Pattern**:
- Format: `===== 2.17.5 =====`
- Detailed technical changelog
- Uses header-based version separation

**Example Version**: 2.17.5

---

## Configuration Details

### Vendor Patterns Added

```php
'mageme.com' => [
    'version_pattern' => '/(\d+\.\d+\.\d+)/i',
    'changelog_pattern' => '/(\d+\.\d+\.\d+)\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d+\s+\d{4}/i',
    'changelog_section' => '/CHANGE\s+LOG(.*?)(?=Frequently|$)/is'
],
'mageworx.com' => [
    'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
    'changelog_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)\s*\(([^)]+)\)/i',
    'changelog_section' => '/<div[^>]*class="[^"]*changelog[^"]*"[^>]*>(.*?)<\/div>/is'
],
'xtento.com' => [
    'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
    'changelog_pattern' => '/=====\s*(\d+\.\d+\.\d+)\s*=====\s*\*(.*?)(?======|$)/s',
    'changelog_section' => '/CHANGELOG(.*?)(?=This extension|$)/is'
]
```

### Package URL Mappings Added

```php
// MageMe modules
'mageme/module-webforms-3' => 'https://mageme.com/magento-2-form-builder.html',
'mageme/module-webforms' => 'https://mageme.com/magento-2-form-builder.html',

// Mageworx modules
'mageworx/module-giftcards' => 'https://www.mageworx.com/magento-2-gift-cards.html',

// XTENTO modules
'xtento/orderexport' => 'https://www.xtento.com/magento-extensions/magento-order-export-module.html',
```

---

## Testing the New Vendors

### Test Commands

```bash
# Test MageMe WebForms
composer vendor:check --url=https://mageme.com/magento-2-form-builder.html

# Test Mageworx Gift Cards
composer vendor:check --url=https://www.mageworx.com/magento-2-gift-cards.html

# Test XTENTO Order Export
composer vendor:check --url=https://www.xtento.com/magento-extensions/magento-order-export-module.html

# Check specific packages
composer vendor:check --packages=mageme/module-webforms-3,mageworx/module-giftcards,xtento/orderexport -v
```

### Expected Output

```
Checking packages: mageme/module-webforms-3, mageworx/module-giftcards, xtento/orderexport
Supported vendors: amasty, mageplaza, bsscommerce, aheadworks, mageme, mageworx, xtento
Note: Only packages from these vendors will be checked

  ✓  mageme/module-webforms-3
      Latest: 3.5.0

  ✓  mageworx/module-giftcards
      Latest: 3.0.5

  ✓  xtento/orderexport
      Latest: 2.17.5
```

---

## Marketplace Links

All three vendors are available on the Adobe Commerce Marketplace:

- **MageMe WebForms**: Search for "WebForms" or "MageMe"
- **Mageworx Gift Cards**: https://commercemarketplace.adobe.com/mageworx-module-giftcards.html
- **XTENTO Order Export**: https://marketplace.magento.com/xtento-orderexport.html

---

## Vendor Characteristics

### MageMe
- **Specialty**: Form builders, custom forms, WebForms solutions
- **Website Structure**: Clean product pages with detailed changelog
- **Version Display**: Clear version numbers in changelog section
- **Changelog Quality**: Excellent - detailed, dated, well-organized

### Mageworx
- **Specialty**: Gift cards, shipping, fees, marketing automation
- **Website Structure**: Professional product pages with feature lists
- **Version Display**: Marketplace-focused with version history
- **Changelog Quality**: Good - includes dates and clear descriptions

### XTENTO
- **Specialty**: Import/Export solutions, order management, integrations
- **Website Structure**: Technical documentation-heavy
- **Version Display**: Detailed technical changelogs
- **Changelog Quality**: Excellent - highly detailed technical changes

---

## Adding More Modules

### MageMe Modules to Add

Other popular MageMe modules you might want to add:

```php
'mageme/module-webforms-customer-registration' => 'URL',
'mageme/module-webforms-hubspot' => 'URL',
'mageme/module-webforms-salesforce' => 'URL',
```

### Mageworx Modules to Add

```php
'mageworx/module-rewardpoints' => 'https://www.mageworx.com/magento-2-reward-points.html',
'mageworx/module-multifees' => 'https://www.mageworx.com/magento-2-extra-fee.html',
'mageworx/module-shippingsuite' => 'https://www.mageworx.com/magento-2-shipping-suite.html',
```

### XTENTO Modules to Add

```php
'xtento/productexport' => 'https://www.xtento.com/magento-extensions/magento-product-feed-export-module.html',
'xtento/trackingimport' => 'https://www.xtento.com/magento-extensions/magento-tracking-import-module.html',
'xtento/stockimport' => 'https://www.xtento.com/magento-extensions/magento-stock-import-module.html',
```

---

## Verification Steps

1. **Pattern Accuracy**: All three vendors' patterns have been tested against their actual product pages
2. **Composer Packages**: Package names verified via Packagist and composer documentation
3. **Marketplace Presence**: All modules confirmed available on Adobe Commerce Marketplace
4. **Version Extraction**: Regex patterns successfully extract version numbers from live pages

---

## Benefits

With these three vendors added:

✅ **Total Vendors Supported**: 7 (was 4)  
✅ **Coverage Increase**: 75% more vendors  
✅ **Popular Modules**: Covers widely-used extensions (forms, gift cards, export)  
✅ **Diverse Use Cases**: Import/export, forms, gift cards, commerce features  

---

## Next Steps

To expand coverage further, consider adding:

1. **Aheadworks** - More modules from their catalog
2. **Fooman** - PDF extensions, surcharges
3. **Webkul** - Marketplace, multi-vendor solutions
4. **Magefan** - Blog, redirects, lazy load
5. **Mirasvit** - Search, SEO, reports

---

## Technical Notes

### Pattern Design Decisions

**MageMe**:
- Flexible version pattern to match anywhere on page
- Month-based changelog pattern for accurate date extraction
- Changelog section targets "CHANGE LOG" heading

**Mageworx**:
- Standard version pattern with optional colon
- Date-in-parentheses changelog format common to their docs
- Fallback to div-based changelog section

**XTENTO**:
- Matches their specific "=====" delimited changelog format
- Handles their technical changelog structure
- Targets "CHANGELOG" section heading

### Why These Patterns Work

1. **Robust**: Handle slight variations in HTML structure
2. **Specific**: Target vendor-specific formatting patterns
3. **Maintainable**: Clear regex with comments possible
4. **Tested**: Verified against live product pages

---

**Version**: 1.2.0  
**Date**: November 23, 2024  
**Author**: John @ GetJohn
