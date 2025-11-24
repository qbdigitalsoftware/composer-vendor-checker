# Quick Reference Card

## Installation
```bash
composer require getjohn/magento2-vendor-checker
```

## Most Common Commands

```bash
# Check everything (supported vendors only)
composer vendor:check

# Show which vendors are supported
composer vendor:check -v

# Check specific package with details
composer vendor:check --packages=amasty/promo -v

# Compare all sources
composer vendor:check --compare-sources

# JSON for scripts
composer vendor:check --json
```

## Supported Vendors
- Amasty (amasty.com)
- Mageplaza (mageplaza.com)
- BSS Commerce (bsscommerce.com)
- Aheadworks (aheadworks.com)

## Command Options

| Option | Short | What It Does |
|--------|-------|--------------|
| `--path` | `-p` | Path to composer.lock |
| `--packages` | - | Specific packages to check |
| `--url` | `-u` | Check single URL |
| `--verbose` | `-v` | Detailed output + show supported vendors |
| `--compare-sources` | `-c` | Compare Composer/Marketplace/Vendor |
| `--json` | `-j` | JSON output |

## Status Symbols

- ✓ UP_TO_DATE - All good
- ↑ UPDATE_AVAILABLE - Newer version exists
- ⚠ AHEAD_OF_VENDOR - Your version is newer (unusual)
- ✗ ERROR - Could not check

## Key Files to Edit

**Add package URLs:**
→ `src/Service/ComposerIntegration.php` → `$packageUrlMappings`

**Add vendor patterns:**
→ `src/Service/VersionChecker.php` → `$vendorPatterns`

## Example: Add New Vendor

### 1. Add Pattern
File: `src/Service/VersionChecker.php`
```php
private $vendorPatterns = [
    // Existing...
    'newvendor.com' => [
        'version_pattern' => '/Version:?\s*(\d+\.\d+\.\d+)/i',
        'changelog_pattern' => '/##\s*(\d+\.\d+\.\d+)\s*\(([^)]+)\)/i',
    ]
];
```

### 2. Add URL Mappings
File: `src/Service/ComposerIntegration.php`
```php
private $packageUrlMappings = [
    // Existing...
    'newvendor/package' => 'https://newvendor.com/product.html',
];
```

Done! New vendor is now supported.

## Troubleshooting

**Command not found:**
```bash
composer dump-autoload
```

**No packages checked:**
- Only supported vendors are checked
- Run with `-v` to see which vendors are supported
- Add URL mappings for your packages

**Version mismatch:**
- This is expected! Sources may be out of sync
- Wait for all sources to sync

## CI/CD Integration

### GitLab
```yaml
vendor-check:
  script:
    - composer vendor:check --json > versions.json
  artifacts:
    paths: [versions.json]
```

### GitHub Actions
```yaml
- name: Check versions
  run: composer vendor:check --json > versions.json
```

### Bash Script
```bash
#!/bin/bash
UPDATES=$(composer vendor:check --json | jq -r '.[] | select(.status=="UPDATE_AVAILABLE") | .package')
if [ ! -z "$UPDATES" ]; then
    echo "Updates: $UPDATES"
fi
```

## Documentation Index

**Getting Started:**
- INDEX.md - Navigation guide
- QUICKSTART.md - Quick commands
- INSTALL.md - Installation

**Features:**
- README.md - Full documentation
- UPDATE_SUMMARY.md - Latest changes
- CHANGELOG.md - Version history

**Technical:**
- STRUCTURE.md - Code architecture
- FLOW_DIAGRAM.md - Execution flow
- VENDOR_FILTERING.md - Filtering logic

## Help & Support

```bash
# Built-in help
composer vendor:check --help

# Check documentation
cat README.md
cat QUICKSTART.md
```

---

**Pro Tips:**

1. Use `-v` regularly to see what's being checked
2. Add custom vendors as you need them
3. Use `--json` for automation
4. Run weekly in CI/CD for update tracking

---

Print this page and keep it handy! 📋
