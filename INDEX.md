# Magento 2 Vendor Checker - Documentation Index

Welcome! This index will help you navigate the documentation and find what you need quickly.

## 🚀 Getting Started

New to this module? Start here:

1. **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - 5-minute overview of what this is and why it exists
2. **[INSTALL.md](INSTALL.md)** - Detailed installation instructions
3. **[QUICKSTART.md](QUICKSTART.md)** - Jump right in with common commands

## 📚 Documentation Files

### User Documentation

| File | Purpose | When to Read |
|------|---------|--------------|
| **README.md** | Complete feature documentation | When you want to understand all features |
| **QUICKSTART.md** | Common command examples | When you want quick copy-paste commands |
| **INSTALL.md** | Installation & configuration | When setting up for the first time |
| **PROJECT_SUMMARY.md** | High-level overview | When you need the big picture |

### Technical Documentation

| File | Purpose | When to Read |
|------|---------|--------------|
| **STRUCTURE.md** | File organization & architecture | When you want to understand the codebase |
| **FLOW_DIAGRAM.md** | Visual command flow diagrams | When you want to see how it works internally |
| **examples/custom-config.php** | Customization examples | When adding custom vendors |

### Legal & Meta

| File | Purpose |
|------|---------|
| **LICENSE** | MIT license terms |
| **.gitignore** | Git ignore rules |
| **CHANGELOG.md** | Version history and updates |
| **VENDOR_FILTERING.md** | Technical deep-dive on filtering logic |

## 📂 Source Code Structure

```
src/
├── ComposerPlugin.php              # Entry point - registers with Composer
├── CommandProvider.php             # Provides the vendor:check command
├── Command/
│   └── VendorCheckCommand.php      # Main command implementation
└── Service/
    ├── VersionChecker.php          # Web scraping & version detection
    └── ComposerIntegration.php     # composer.lock integration
```

## 🎯 Quick Navigation by Task

### "I want to..."

#### Install the module
→ [INSTALL.md](INSTALL.md)

#### Run my first check
→ [QUICKSTART.md](QUICKSTART.md) - Section: "Basic Usage"

#### Check specific packages
→ [QUICKSTART.md](QUICKSTART.md) - Section: "Common Workflows"

#### Understand all available options
→ [README.md](README.md) - Section: "Command Options"

#### Add a custom vendor
→ [INSTALL.md](INSTALL.md) - Section: "Configuration"
→ [examples/custom-config.php](examples/custom-config.php)

#### Integrate with CI/CD
→ [INSTALL.md](INSTALL.md) - Section: "Advanced Usage"
→ [QUICKSTART.md](QUICKSTART.md) - Section: "Integration Examples"

#### Understand the code architecture
→ [STRUCTURE.md](STRUCTURE.md)
→ [FLOW_DIAGRAM.md](FLOW_DIAGRAM.md)
→ [VENDOR_FILTERING.md](VENDOR_FILTERING.md) - How vendor filtering works

#### See what changed from the old tool
→ [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Section: "Key Changes"
→ [CHANGELOG.md](CHANGELOG.md) - Version history

#### Troubleshoot an issue
→ [INSTALL.md](INSTALL.md) - Section: "Troubleshooting"

## 🔍 Find Information By Topic

### Installation
- [INSTALL.md](INSTALL.md) - Complete installation guide
- [README.md](README.md) - Quick installation section

### Usage & Examples
- [QUICKSTART.md](QUICKSTART.md) - Quick command reference
- [README.md](README.md) - Detailed usage documentation
- [examples/custom-config.php](examples/custom-config.php) - Code examples

### Commands & Options
- [README.md](README.md) - Section: "Command Options"
- [QUICKSTART.md](QUICKSTART.md) - Common command patterns

### Architecture & Code
- [STRUCTURE.md](STRUCTURE.md) - File structure & organization
- [FLOW_DIAGRAM.md](FLOW_DIAGRAM.md) - Execution flow diagrams
- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Technical overview

### Customization
- [INSTALL.md](INSTALL.md) - Section: "Configuration"
- [examples/custom-config.php](examples/custom-config.php) - Examples
- [STRUCTURE.md](STRUCTURE.md) - Extension points

### CI/CD Integration
- [INSTALL.md](INSTALL.md) - Section: "Advanced Usage"
- [QUICKSTART.md](QUICKSTART.md) - Section: "Integration Examples"

## 📖 Recommended Reading Order

### For End Users
1. PROJECT_SUMMARY.md (5 min) - Understand what this does
2. INSTALL.md (10 min) - Get it installed
3. QUICKSTART.md (5 min) - Start using it
4. README.md (optional) - Deep dive when needed

### For Developers
1. PROJECT_SUMMARY.md - Overview
2. STRUCTURE.md - Understand the architecture
3. FLOW_DIAGRAM.md - See how it works
4. Source code in `src/` - Read the implementation

### For DevOps/CI Engineers
1. QUICKSTART.md - Learn the commands
2. INSTALL.md - Advanced Usage section
3. README.md - Command Options section

## 🎓 Learning Path

### Beginner (Just want to use it)
```
PROJECT_SUMMARY.md
    ↓
INSTALL.md
    ↓
QUICKSTART.md
    ↓
You're ready!
```

### Intermediate (Want to customize)
```
Above, plus:
    ↓
INSTALL.md (Configuration section)
    ↓
examples/custom-config.php
    ↓
STRUCTURE.md (Extension points)
```

### Advanced (Want to contribute/modify)
```
Above, plus:
    ↓
STRUCTURE.md (full read)
    ↓
FLOW_DIAGRAM.md
    ↓
Source code review
```

## 🔧 Quick Reference Cards

### Installation One-Liner
```bash
composer require getjohn/magento2-vendor-checker
```

### Most Common Commands
```bash
# Check everything
composer vendor:check

# Check specific package with details
composer vendor:check --packages=amasty/promo -v

# JSON output for scripts
composer vendor:check --json
```

### File to Edit for Common Tasks
- Add package URL: `src/Service/ComposerIntegration.php` ($packageUrlMappings)
- Add vendor pattern: `src/Service/VersionChecker.php` ($vendorPatterns)
- Modify command options: `src/Command/VendorCheckCommand.php` (configure method)

## 📞 Support & Contact

- **Author**: John @ GetJohn
- **Email**: john@getjohn.co.uk
- **Website**: https://getjohn.co.uk

## 🗺️ Document Map

```
Documentation/
│
├── Getting Started
│   ├── PROJECT_SUMMARY.md    ← Start here for overview
│   ├── INSTALL.md            ← Installation guide
│   └── QUICKSTART.md         ← Quick commands
│
├── Complete Reference
│   ├── README.md             ← Full documentation
│   └── FLOW_DIAGRAM.md       ← How it works
│
├── Technical
│   └── STRUCTURE.md          ← Architecture & code
│
├── Examples
│   └── examples/
│       └── custom-config.php ← Code examples
│
└── Meta
    ├── LICENSE               ← Legal
    ├── INDEX.md              ← This file
    └── .gitignore            ← Git config
```

---

**Pro Tip**: Keep this INDEX.md open as a reference while exploring the other documentation!
