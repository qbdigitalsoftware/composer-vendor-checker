# Command Flow Diagram

## How `composer vendor:check` Works

```
┌──────────────────────────────────────────────────────────────────────────┐
│  User Terminal                                                            │
│  $ composer vendor:check --packages=amasty/promo -v                      │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Composer Core                                                            │
│  - Loads installed plugins                                                │
│  - Finds getjohn/magento2-vendor-checker                                  │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  ComposerPlugin.php (implements PluginInterface, Capable)                 │
│                                                                           │
│  getCapabilities() returns:                                               │
│    CommandProviderCapability::class => CommandProvider::class            │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  CommandProvider.php (implements CommandProviderCapability)               │
│                                                                           │
│  getCommands() returns:                                                   │
│    [ new VendorCheckCommand() ]                                           │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  VendorCheckCommand.php (extends BaseCommand)                             │
│                                                                           │
│  configure():                                                             │
│    - Sets name: "vendor:check"                                            │
│    - Defines options: --packages, --url, --verbose, etc.                  │
│                                                                           │
│  execute($input, $output):                                                │
│    1. Parse command options                                               │
│    2. Determine mode (single URL / packages / all)                        │
│    3. Call appropriate service methods                                    │
│    4. Format and display results                                          │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ├─────────────────┐
                                 ▼                 ▼
┌───────────────────────────────────────┐  ┌──────────────────────────────┐
│  ComposerIntegration.php              │  │  VersionChecker.php          │
│                                       │  │                              │
│  Methods:                             │  │  Methods:                    │
│  - getInstalledPackages()             │  │  - getVendorVersion($url)    │
│  - checkForUpdates()                  │  │  - checkMultiplePackages()   │
│  - getPackageUrlMappings()            │  │  - extractVersion()          │
│  - compareVersions()                  │  │  - extractChangelog()        │
│  - generateReport()                   │  │  - getComposerVersion()      │
│                                       │  │  - getMarketplaceVersion()   │
│  Uses:                                │  │                              │
│  - Reads composer.lock                │  │  Uses:                       │
│  - Maintains package->URL mappings    │  │  - GuzzleHttp\Client         │
│  - Filters by vendor                  │  │  - DOM parsing               │
│  - Creates human-readable reports     │  │  - Regex pattern matching    │
└───────────────┬───────────────────────┘  └────────┬─────────────────────┘
                │                                    │
                │                                    │
                └────────────┬───────────────────────┘
                             ▼
                ┌─────────────────────────────────┐
                │  External Resources             │
                │                                 │
                │  - composer.lock file           │
                │  - Vendor websites (HTTP)       │
                │  - Magento Marketplace          │
                │  - composer show command        │
                └────────────┬────────────────────┘
                             ▼
                ┌─────────────────────────────────┐
                │  Results Processing             │
                │                                 │
                │  Array of results:              │
                │  [                              │
                │    package => 'amasty/promo',   │
                │    installed => '2.22.0',       │
                │    latest => '2.23.1',          │
                │    status => 'UPDATE_AVAILABLE',│
                │    changelog => [...]           │
                │  ]                              │
                └────────────┬────────────────────┘
                             ▼
                ┌─────────────────────────────────┐
                │  Output Formatting              │
                │                                 │
                │  If --json:                     │
                │    → JSON to stdout             │
                │  Else:                          │
                │    → Human-readable report      │
                │    → Status symbols (✓ ↑ ⚠ ✗)   │
                │    → Summary statistics         │
                └────────────┬────────────────────┘
                             ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  User Terminal                                                            │
│                                                                           │
│  ╔════════════════════════════════════════════════════════╗              │
│  ║        Vendor Version Check Report                     ║              │
│  ╚════════════════════════════════════════════════════════╝              │
│                                                                           │
│    ↑  amasty/promo                                                        │
│        Installed: 2.22.0              Latest: 2.23.1                      │
│        Recent changes:                                                    │
│          • 2.23.1 - Sep 15, 2024                                          │
│                                                                           │
│  ─────────────────────────────────────────────────────────               │
│  Summary: 0 up-to-date, 1 updates available, 0 errors                    │
└──────────────────────────────────────────────────────────────────────────┘
```

## Execution Flow by Mode

### Mode 1: Single URL Check
```
--url=https://...
    ↓
VendorCheckCommand::checkSingleUrl()
    ↓
VersionChecker::getVendorVersion($url)
    ↓
HTTP GET → Parse HTML → Extract version & changelog
    ↓
Display result
```

### Mode 2: Specific Packages
```
--packages=pkg1,pkg2
    ↓
VendorCheckCommand::checkMultiplePackages()
    ↓
ComposerIntegration::getPackageUrlMappings()
    ↓
VersionChecker::checkMultiplePackages()
    ↓
For each package:
  - Get Composer version (if --compare-sources)
  - Get Marketplace version (if --compare-sources)
  - Get Vendor site version
    ↓
Compare all sources
    ↓
Display results
```

### Mode 3: All Packages
```
Default (no specific packages)
    ↓
VendorCheckCommand::checkAllPackages()
    ↓
ComposerIntegration::getInstalledPackages()
    ↓
Read composer.lock
    ↓
Filter by known vendor packages
    ↓
For each package:
  ComposerIntegration::checkForUpdates()
    ↓
  VersionChecker::getVendorVersion()
    ↓
Generate report
    ↓
Display results
```

## Key Design Patterns

### 1. Plugin Pattern
```
Composer → Plugin Interface → Custom Commands
```
The module implements Composer's plugin API to register custom commands.

### 2. Command Pattern
```
User Input → Command Object → Service Execution → Formatted Output
```
VendorCheckCommand orchestrates the workflow without containing business logic.

### 3. Service Layer Pattern
```
Command → Services → External Resources
```
Business logic isolated in service classes (VersionChecker, ComposerIntegration).

### 4. Strategy Pattern
```
Different vendors → Different parsing strategies
```
Each vendor has its own HTML parsing patterns in $vendorPatterns array.

## Data Flow

```
composer.lock
    ↓
Package Names + Versions
    ↓
Package URL Mappings (ComposerIntegration)
    ↓
HTTP Requests (VersionChecker + Guzzle)
    ↓
HTML Content
    ↓
Regex Pattern Matching
    ↓
Extracted Versions + Changelog
    ↓
Version Comparison
    ↓
Status Determination (UP_TO_DATE, UPDATE_AVAILABLE, etc.)
    ↓
Report Generation
    ↓
Output (Human-readable or JSON)
    ↓
User Terminal
```

## Error Handling Flow

```
Try:
  HTTP Request
    ↓
  Parse HTML
    ↓
  Extract Version
    ↓
Catch:
  GuzzleException → Network error
  DOMException → Parsing error
  RegexException → Pattern mismatch
    ↓
Return:
  Error status in result array
    ↓
Display:
  ✗ symbol + error message
```

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
