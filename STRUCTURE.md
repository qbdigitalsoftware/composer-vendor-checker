# Directory Structure

```
magento2-vendor-checker/
│
├── composer.json                          # Composer configuration (type: composer-plugin)
├── registration.php                       # Magento 2 module registration
├── LICENSE                                # MIT License
├── README.md                              # Full documentation
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

### src/

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

## Extension Points

Want to customize? Here's where:

1. **Add Package URLs**
   - `ComposerIntegration::$packageUrlMappings`

2. **Add Vendor Patterns**
   - `VersionChecker::$vendorPatterns`

3. **Add Command Options**
   - `VendorCheckCommand::configure()`

4. **Change Output Format**
   - `VendorCheckCommand::display*()` methods

5. **Add Version Sources**
   - `VersionChecker::checkMultiplePackages()`
