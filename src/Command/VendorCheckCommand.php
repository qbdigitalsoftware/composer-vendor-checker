<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Command;

use Composer\Command\BaseCommand;
use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Service\VersionChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composer command to check vendor websites for module updates
 */
class VendorCheckCommand extends BaseCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setName('vendor:check')
            ->setDescription('Check vendor websites for latest module versions')
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Path to composer.lock file',
                './composer.lock'
            )
            ->addOption(
                'packages',
                null,
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of package names to check (e.g., amasty/promo,mageplaza/layered-navigation)'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Single vendor URL to check'
            )
            ->addOption(
                'verbose',
                'v',
                InputOption::VALUE_NONE,
                'Show detailed output'
            )
            ->addOption(
                'compare-sources',
                'c',
                InputOption::VALUE_NONE,
                'Compare versions across Composer, Marketplace, and vendor sites'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output results as JSON'
            )
            ->setHelp(<<<EOF
The <info>vendor:check</info> command checks vendor websites for the latest module versions.

<info>Supported Vendors:</info>
  Amasty, Mageplaza, BSS Commerce, Aheadworks, MageMe, Mageworx, XTENTO
  
  Note: When checking all packages, only packages from supported vendors will be checked.
  Use -v to see the list of supported vendors.

<info>Usage:</info>

  Check all installed packages (from supported vendors only):
    <comment>composer vendor:check</comment>

  Check specific packages:
    <comment>composer vendor:check --packages=amasty/promo,mageplaza/layered-navigation</comment>

  Check a single vendor URL:
    <comment>composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html</comment>

  Compare versions from multiple sources:
    <comment>composer vendor:check --compare-sources --packages=amasty/promo</comment>

  Show detailed output with supported vendors:
    <comment>composer vendor:check -v</comment>

  Output as JSON:
    <comment>composer vendor:check --json</comment>

EOF
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getOption('path');
        $packages = $input->getOption('packages');
        $url = $input->getOption('url');
        $verbose = $input->getOption('verbose');
        $compareSources = $input->getOption('compare-sources');
        $jsonOutput = $input->getOption('json');

        // Single URL check
        if ($url) {
            return $this->checkSingleUrl($url, $output, $verbose, $jsonOutput);
        }

        // Multiple packages check
        if ($packages) {
            $packageList = array_map('trim', explode(',', $packages));
            return $this->checkMultiplePackages($packageList, $output, $verbose, $compareSources, $jsonOutput);
        }

        // Check all installed packages
        return $this->checkAllPackages($path, $output, $verbose, $compareSources, $jsonOutput);
    }

    /**
     * Check a single vendor URL
     *
     * @param string $url
     * @param OutputInterface $output
     * @param bool $verbose
     * @param bool $jsonOutput
     * @return int
     */
    protected function checkSingleUrl($url, OutputInterface $output, $verbose, $jsonOutput)
    {
        $checker = new VersionChecker();
        
        if (!$jsonOutput) {
            $output->writeln("<info>Checking vendor URL:</info> $url");
            $output->writeln('');
        }

        try {
            $result = $checker->getVendorVersion($url);
            
            if ($jsonOutput) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
            } else {
                $this->displaySingleResult($result, $output, $verbose);
            }
            
            return 0;
        } catch (\Exception $e) {
            if ($jsonOutput) {
                $output->writeln(json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT));
            } else {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");
            }
            return 1;
        }
    }

    /**
     * Check multiple specific packages
     *
     * @param array $packages
     * @param OutputInterface $output
     * @param bool $verbose
     * @param bool $compareSources
     * @param bool $jsonOutput
     * @return int
     */
    protected function checkMultiplePackages(array $packages, OutputInterface $output, $verbose, $compareSources, $jsonOutput)
    {
        $checker = new VersionChecker();
        $integration = new ComposerIntegration('./composer.lock');

        if (!$jsonOutput) {
            $output->writeln("<info>Checking packages:</info> " . implode(', ', $packages));
            $output->writeln('');
        }

        $results = $checker->checkMultiplePackages($packages, [
            'include_composer_show' => $compareSources,
            'include_marketplace' => $compareSources,
            'include_vendor_site' => true,
            'vendor_urls' => $integration->getPackageUrlMappings()
        ]);

        if ($jsonOutput) {
            $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->displayResults($results, $output, $verbose, $compareSources);
        }

        return 0;
    }

    /**
     * Check all installed packages from composer.lock
     *
     * @param string $path
     * @param OutputInterface $output
     * @param bool $verbose
     * @param bool $compareSources
     * @param bool $jsonOutput
     * @return int
     */
    protected function checkAllPackages($path, OutputInterface $output, $verbose, $compareSources, $jsonOutput)
    {
        if (!file_exists($path)) {
            $output->writeln("<error>composer.lock not found at: $path</error>");
            return 1;
        }

        $integration = new ComposerIntegration($path);
        
        if (!$jsonOutput) {
            $output->writeln("<info>Checking all installed packages from:</info> $path");
            
            // Show supported vendors in verbose mode
            if ($verbose) {
                $supportedVendors = $integration->getSupportedVendors();
                $output->writeln("<comment>Supported vendors:</comment> " . implode(', ', $supportedVendors));
                $output->writeln("<comment>Note: Only packages from these vendors will be checked</comment>");
            }
            
            $output->writeln('');
        }

        $results = $integration->checkForUpdates($verbose);

        if ($jsonOutput) {
            $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $report = $integration->generateReport($results);
            $output->writeln($report);
        }

        return 0;
    }

    /**
     * Display a single result
     *
     * @param array $result
     * @param OutputInterface $output
     * @param bool $verbose
     */
    protected function displaySingleResult(array $result, OutputInterface $output, $verbose)
    {
        if (isset($result['error'])) {
            $output->writeln("<error>Error: {$result['error']}</error>");
            return;
        }

        $output->writeln("<info>Latest Version:</info> {$result['latest_version']}");
        
        if ($verbose && isset($result['changelog'])) {
            $output->writeln('');
            $output->writeln('<info>Recent Changes:</info>');
            foreach (array_slice($result['changelog'], 0, 5) as $entry) {
                $output->writeln("  • {$entry['version']} - {$entry['date']}");
                if (!empty($entry['changes'])) {
                    foreach (array_slice($entry['changes'], 0, 3) as $change) {
                        $output->writeln("    - $change");
                    }
                }
            }
        }
    }

    /**
     * Display multiple results
     *
     * @param array $results
     * @param OutputInterface $output
     * @param bool $verbose
     * @param bool $compareSources
     */
    protected function displayResults(array $results, OutputInterface $output, $verbose, $compareSources)
    {
        foreach ($results as $package => $data) {
            $output->writeln("<info>Package:</info> $package");
            
            if ($compareSources) {
                $output->writeln("  Composer:  " . ($data['composer_version'] ?? 'N/A'));
                $output->writeln("  Marketplace: " . ($data['marketplace_version'] ?? 'N/A'));
                $output->writeln("  Vendor Site: " . ($data['vendor_version'] ?? 'N/A'));
                
                if (isset($data['all_match'])) {
                    $status = $data['all_match'] ? '<info>✓ All sources match</info>' : '<comment>⚠ Version mismatch</comment>';
                    $output->writeln("  Status: $status");
                }
            } else {
                $output->writeln("  Latest: " . ($data['vendor_version'] ?? 'N/A'));
            }
            
            $output->writeln('');
        }
    }
}
