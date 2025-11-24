# Quick Start Guide

## Installation

```bash
composer require getjohn/magento2-vendor-checker
```

## Basic Usage

### 1. Check everything
```bash
composer vendor:check
```

### 2. Check specific modules
```bash
composer vendor:check --packages=amasty/promo,mageplaza/layered-navigation-m2-pro
```

### 3. Get detailed changelog info
```bash
composer vendor:check -v
```

### 4. Compare all sources
```bash
composer vendor:check --compare-sources
```

### 5. Output as JSON for scripting
```bash
composer vendor:check --json > version-report.json
```

## Common Workflows

### Before Running `composer update`
```bash
# See what versions are actually available
composer vendor:check --compare-sources
```

### Weekly Update Check
```bash
# Add to your cron or CI pipeline
composer vendor:check --json | \
  jq '.[] | select(.status=="UPDATE_AVAILABLE") | .package'
```

### Debugging Version Mismatches
```bash
# Check why a specific package won't update
composer vendor:check \
  --compare-sources \
  --packages=amasty/promo \
  -v
```

## Output Status Codes

- ✓ **UP_TO_DATE** - Your version matches the vendor's latest
- ↑ **UPDATE_AVAILABLE** - Vendor has a newer version
- ⚠ **AHEAD_OF_VENDOR** - You're running a newer version (unusual)
- ✗ **ERROR** - Could not check this package

## Integration Examples

### Shell Script
```bash
#!/bin/bash
# check-updates.sh

UPDATES=$(composer vendor:check --json | jq -r '.[] | select(.status=="UPDATE_AVAILABLE") | .package')

if [ -z "$UPDATES" ]; then
    echo "All packages up to date!"
    exit 0
else
    echo "Updates available for:"
    echo "$UPDATES"
    exit 1
fi
```

### PHP Script
```php
<?php
use GetJohn\VendorChecker\Service\ComposerIntegration;

$checker = new ComposerIntegration('./composer.lock');
$results = $checker->checkForUpdates(true);

foreach ($results as $result) {
    if ($result['status'] === 'UPDATE_AVAILABLE') {
        echo sprintf(
            "%s: %s → %s\n",
            $result['package'],
            $result['installed_version'],
            $result['latest_version']
        );
    }
}
```

### GitLab CI
```yaml
vendor-version-check:
  stage: test
  script:
    - composer vendor:check --json > versions.json
    - |
      if jq -e '.[] | select(.status=="UPDATE_AVAILABLE")' versions.json > /dev/null; then
        echo "Updates available - review versions.json"
        exit 1
      fi
  artifacts:
    paths:
      - versions.json
    when: always
```

## Tips

1. **Run regularly** - Set up a weekly check in your CI pipeline
2. **Compare sources** - Use `--compare-sources` to spot sync issues
3. **Verbose mode** - Use `-v` to see what's changed in each version
4. **Custom paths** - Use `--path` if your composer.lock is in a non-standard location
5. **JSON output** - Use `--json` for integration with other tools

## Need Help?

- Check the full README.md for detailed documentation
- Open an issue on GitHub for bugs or feature requests
- Review the source code in `src/` for customization options
