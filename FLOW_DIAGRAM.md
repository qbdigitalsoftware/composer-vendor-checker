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

