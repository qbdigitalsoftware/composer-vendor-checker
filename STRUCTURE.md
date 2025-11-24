# Directory Structure

```
magento2-vendor-checker/
│
├── composer.json                          # Composer configuration (type: composer-plugin)
├── registration.php                       # Magento 2 module registration
├── LICENSE                                # MIT License
├── README.md                              # Full documentation
├── QUICKSTART.md                          # Quick start guide
├── .gitignore                             # Git ignore rules
│
├── etc/
│   └── module.xml                         # Magento 2 module configuration
│
├── src/
│   ├── ComposerPlugin.php                 # Main plugin class (implements PluginInterface)
│   ├── CommandProvider.php                # Provides commands to Composer
│   │
│   ├── Command/
│   │   └── VendorCheckCommand.php         # Main CLI command (composer vendor:check)
│   │
│   └── Service/
│       ├── VersionChecker.php             # Core version checking & web scraping logic
│       └── ComposerIntegration.php        # Integration with composer.lock
│
└── examples/
    └── custom-config.php                  # Example custom configuration
```

## Key Files Explained

### Root Level

- **composer.json** - Defines this as a Composer plugin with type `composer-plugin`
- **registration.php** - Registers the module with Magento 2
- **README.md** - Complete documentation with examples and use cases
- **QUICKSTART.md** - Quick reference guide for common commands

### etc/

- **module.xml** - Standard Magento 2 module declaration

### src/

Main source code organized by responsibility:

- **ComposerPlugin.php** - Entry point for Composer plugin system
  - Implements `PluginInterface` and `Capable`
  - Returns `CommandProvider` capability

- **CommandProvider.php** - Registers custom commands
  - Implements `CommandProviderCapability`
  - Returns array of command instances

### src/Command/

- **VendorCheckCommand.php** - The actual CLI command
  - Extends Symfony's `BaseCommand`
  - Handles all command options (--packages, --url, --compare-sources, etc.)
  - Coordinates between services to execute checks
  - Formats and displays output

### src/Service/

Business logic separated into focused services:

- **VersionChecker.php** - Core checking logic
  - HTTP client configuration
  - Vendor-specific parsing patterns
  - Web scraping and DOM parsing
  - Version extraction
  - Changelog parsing

- **ComposerIntegration.php** - Composer integration
  - Reads and parses composer.lock
  - Maintains package URL mappings
  - Filters packages by vendor
  - Version comparison logic
  - Report generation

### examples/

- **custom-config.php** - Shows how to extend the module with custom vendors

## How It All Works Together

1. **Composer loads the plugin** (ComposerPlugin.php)
2. **Plugin registers commands** (via CommandProvider.php)
3. **User runs `composer vendor:check`**
4. **VendorCheckCommand executes** with provided options
5. **ComposerIntegration** reads composer.lock and gets package list
6. **VersionChecker** scrapes vendor websites for each package
7. **Results are formatted** and displayed to the user

## Installation Methods

### As a Composer Plugin (Recommended)
Install globally or per-project:
```bash
composer require getjohn/magento2-vendor-checker
```

The command `composer vendor:check` becomes available immediately.

### As a Magento 2 Module
Place in `app/code/GetJohn/VendorChecker/` and run:
```bash
php bin/magento module:enable GetJohn_VendorChecker
php bin/magento setup:upgrade
```

Both methods work simultaneously - it's both a Composer plugin AND a Magento module!
