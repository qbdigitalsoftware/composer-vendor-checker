# Vendor Filtering Logic - Visual Guide

## How Vendor Filtering Works

```
┌─────────────────────────────────────────────────────────────┐
│  User runs: composer vendor:check                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  ComposerIntegration::getInstalledPackages()                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 1: Read composer.lock                                 │
│                                                              │
│  All packages in composer.lock:                             │
│  - amasty/promo                                              │
│  - amasty/shopby                                             │
│  - mageplaza/layered-navigation                              │
│  - magento/framework                                         │
│  - symfony/console                                           │
│  - guzzlehttp/guzzle                                         │
│  - randomvendor/module                                       │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 2: Get Supported Vendors from Patterns                │
│                                                              │
│  VersionChecker::$vendorPatterns contains:                   │
│  - amasty.com                                                │
│  - mageplaza.com                                             │
│  - bsscommerce.com                                           │
│  - aheadworks.com                                            │
│                                                              │
│  Convert to vendor names:                                    │
│  - amasty                                                    │
│  - mageplaza                                                 │
│  - bsscommerce                                               │
│  - aheadworks                                                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 3: Filter Packages by Vendor                          │
│                                                              │
│  For each package in composer.lock:                         │
│    Extract vendor name (part before '/')                    │
│    Check if vendor is in supported list                     │
│                                                              │
│  ✓ amasty/promo           → vendor: amasty     → KEEP       │
│  ✓ amasty/shopby          → vendor: amasty     → KEEP       │
│  ✓ mageplaza/layered-...  → vendor: mageplaza  → KEEP       │
│  ✗ magento/framework      → vendor: magento    → SKIP       │
│  ✗ symfony/console        → vendor: symfony    → SKIP       │
│  ✗ guzzlehttp/guzzle      → vendor: guzzlehttp → SKIP       │
│  ✗ randomvendor/module    → vendor: randomvendor → SKIP     │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 4: Filter by URL Mappings                             │
│                                                              │
│  Only keep packages that have a URL mapping:                │
│                                                              │
│  ✓ amasty/promo     → Has URL → KEEP                        │
│  ✓ amasty/shopby    → Has URL → KEEP                        │
│  ✓ mageplaza/...    → Has URL → KEEP                        │
│  ✗ amasty/unknown   → No URL  → SKIP                        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 5: Return Filtered Package List                       │
│                                                              │
│  Final packages to check:                                   │
│  - amasty/promo                                              │
│  - amasty/shopby                                             │
│  - mageplaza/layered-navigation                              │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  Check each package's vendor website                        │
│  (Only 3 packages checked instead of 7!)                    │
└─────────────────────────────────────────────────────────────┘
```

## Code Flow

### VersionChecker.php

```php
class VersionChecker {
    // Define supported vendors with their patterns
    private $vendorPatterns = [
        'amasty.com' => [...],
        'mageplaza.com' => [...],
        'bsscommerce.com' => [...],
        'aheadworks.com' => [...]
    ];
    
    // Get list of supported vendor domains
    public function getSupportedVendors() {
        return array_keys($this->vendorPatterns);
        // Returns: ['amasty.com', 'mageplaza.com', ...]
    }
    
    // Extract vendor from package name
    public function getVendorFromPackage($packageName) {
        $parts = explode('/', $packageName);
        return $parts[0];
        // 'amasty/promo' → 'amasty'
    }
}
```

### ComposerIntegration.php

```php
class ComposerIntegration {
    public function getInstalledPackages() {
        // 1. Get supported vendor domains
        $supportedVendorDomains = $this->versionChecker->getSupportedVendors();
        // ['amasty.com', 'mageplaza.com', ...]
        
        // 2. Convert domains to vendor names
        $supportedVendors = array_map(function($domain) {
            return explode('.', $domain)[0];
        }, $supportedVendorDomains);
        // ['amasty', 'mageplaza', ...]
        
        // 3. Filter packages
        foreach ($lockData['packages'] as $package) {
            $vendor = $this->versionChecker->getVendorFromPackage($package['name']);
            
            // Skip if not in supported list
            if (!in_array($vendor, $supportedVendors)) {
                continue;
            }
            
            // Skip if no URL mapping
            if (!isset($this->packageUrlMappings[$package['name']])) {
                continue;
            }
            
            // Keep this package
            $packages[$package['name']] = [...];
        }
    }
    
    public function getSupportedVendors() {
        // Return vendor names for display
        return ['amasty', 'mageplaza', 'bsscommerce', 'aheadworks'];
    }
}
```

## Decision Tree

```
                    Package Found in composer.lock
                               │
                               ▼
                    ┌──────────────────────┐
                    │ Extract vendor name  │
                    │ (part before '/')    │
                    └──────────┬───────────┘
                               │
                ┌──────────────┴──────────────┐
                │                             │
                ▼                             ▼
    Is vendor in supported list?        Is vendor in supported list?
            YES                               NO
                │                             │
                ▼                             ▼
    Does package have URL mapping?      ┌─────────────┐
            YES          NO              │   SKIP      │
                │         │              │  Package    │
                ▼         ▼              └─────────────┘
         ┌─────────┐  ┌─────────┐
         │  CHECK  │  │  SKIP   │
         │ Package │  │ Package │
         └─────────┘  └─────────┘
```

## Example Scenarios

### Scenario 1: Amasty Package with Mapping
```
Package: amasty/promo
  ↓
Vendor: amasty
  ↓
In supported list? YES (amasty.com pattern exists)
  ↓
Has URL mapping? YES (https://amasty.com/special-promotions...)
  ↓
Result: ✓ CHECK THIS PACKAGE
```

### Scenario 2: Magento Core Package
```
Package: magento/framework
  ↓
Vendor: magento
  ↓
In supported list? NO (no magento.com pattern)
  ↓
Result: ✗ SKIP THIS PACKAGE
```

### Scenario 3: Amasty Package Without Mapping
```
Package: amasty/unknown-module
  ↓
Vendor: amasty
  ↓
In supported list? YES (amasty.com pattern exists)
  ↓
Has URL mapping? NO (not in $packageUrlMappings)
  ↓
Result: ✗ SKIP THIS PACKAGE
```

### Scenario 4: Random Vendor
```
Package: randomvendor/extension
  ↓
Vendor: randomvendor
  ↓
In supported list? NO (no randomvendor.com pattern)
  ↓
Result: ✗ SKIP THIS PACKAGE (early exit)
```

## Benefits Illustrated

### Without Filtering (Old Behavior)
```
100 packages in composer.lock
    ↓
Check ALL 100 packages
    ↓
5 successful checks
95 errors/failures
    ↓
Cluttered output with many errors
Slow execution (100 HTTP requests attempted)
```

### With Filtering (New Behavior)
```
100 packages in composer.lock
    ↓
Filter to 5 supported vendor packages
    ↓
Check ONLY 5 packages
    ↓
5 successful checks
0 errors/failures
    ↓
Clean output
Fast execution (5 HTTP requests)
```

## Adding New Vendor Support

### What You Need to Do

```
1. Add Pattern to VersionChecker
   ↓
   private $vendorPatterns = [
       'newvendor.com' => [...]
   ];
   
2. Add URL Mappings to ComposerIntegration
   ↓
   private $packageUrlMappings = [
       'newvendor/package' => 'https://...'
   ];

RESULT: Vendor automatically included in filtering!
```

### What Happens Automatically

```
Pattern Added (newvendor.com)
    ↓
getSupportedVendors() returns it
    ↓
Converted to vendor name (newvendor)
    ↓
Packages with vendor=newvendor are now checked
    ↓
No code changes needed anywhere else!
```

---

**The key insight**: The list of supported vendors is **derived from the patterns**, not hardcoded. This makes the system self-maintaining and consistent.
