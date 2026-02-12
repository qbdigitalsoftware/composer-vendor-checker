<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Command;

use Composer\Command\BaseCommand;
use GetJohn\VendorChecker\Output\OutputFormatter;
use GetJohn\VendorChecker\Output\ProgressReporter;
use GetJohn\VendorChecker\Service\ComposerIntegration;
use GetJohn\VendorChecker\Service\ResultCache;
use GetJohn\VendorChecker\Service\VersionChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composer command to check installed packages for available updates.
 */
class VendorCheckCommand extends BaseCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('vendor:check')
            ->setDescription('Check installed packages for available updates')
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
                'Comma-separated list of package names to check'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Single vendor URL to check'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: table, json, csv',
                'table'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Write results to file path'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output results as JSON (alias for --format=json)'
            )
            ->addOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'Skip reading cached results'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Clear cache before running'
            )
            ->addOption(
                'cache-ttl',
                null,
                InputOption::VALUE_OPTIONAL,
                'Cache TTL in seconds',
                3600
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to packages.php config file'
            )
            ->setHelp(<<<EOF
The <info>vendor:check</info> command checks installed packages for available updates.

<info>Usage:</info>

  Check all installed packages:
    <comment>composer vendor:check</comment>

  Check specific packages:
    <comment>composer vendor:check --packages=amasty/promo,stripe/stripe-payments</comment>

  Check a single vendor URL:
    <comment>composer vendor:check --url=https://amasty.com/admin-actions-log-for-magento-2.html</comment>

  Show detailed output:
    <comment>composer vendor:check -v</comment>

  Output as JSON:
    <comment>composer vendor:check --format=json</comment>

  Output as CSV to file:
    <comment>composer vendor:check --format=csv --output=report.csv</comment>

  Skip cache:
    <comment>composer vendor:check --no-cache</comment>

  Clear cache and re-check:
    <comment>composer vendor:check --clear-cache</comment>

EOF
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('path');
        $packages = $input->getOption('packages');
        $url = $input->getOption('url');
        $configPath = $input->getOption('config');
        $outputPath = $input->getOption('output');
        $noCache = $input->getOption('no-cache');
        $clearCache = $input->getOption('clear-cache');
        $cacheTtl = (int) $input->getOption('cache-ttl');

        // Resolve format (--json is alias for --format=json)
        $format = $input->getOption('json') ? 'json' : $input->getOption('format');

        // Single URL check (doesn't need ComposerIntegration)
        if ($url) {
            return $this->checkSingleUrl($url, $output, $format);
        }

        if (!file_exists($path)) {
            $output->writeln("<error>composer.lock not found at: $path</error>");
            return 2;
        }

        // Resolve companion files from lock file directory
        $lockDir = dirname(realpath($path) ?: $path);
        $composerJsonPath = $lockDir . '/composer.json';
        $authJsonPath = $lockDir . '/auth.json';

        // Set up cache
        $cache = null;
        if (!$noCache) {
            $cacheDir = $lockDir . '/.vendor-check-cache';
            $cache = new ResultCache($cacheDir, $cacheTtl);

            if ($clearCache) {
                $cache->clear();
                if ($format === 'table') {
                    $output->writeln('<comment>Cache cleared.</comment>');
                }
            }
        }

        $integration = new ComposerIntegration(
            $path,
            file_exists($composerJsonPath) ? $composerJsonPath : null,
            file_exists($authJsonPath) ? $authJsonPath : null,
            $configPath,
            $cache
        );

        // Parse --packages filter
        $packageFilter = [];
        if ($packages) {
            $packageFilter = array_map('trim', explode(',', $packages));
        }

        // Show header for table format
        if ($format === 'table') {
            $output->writeln("<info>Checking packages from:</info> $path");
            if ($output->isVerbose()) {
                if (file_exists($authJsonPath)) {
                    $output->writeln("<comment>Private repos:</comment> enabled (auth.json found)");
                }
                if (!$noCache) {
                    $output->writeln("<comment>Cache TTL:</comment> {$cacheTtl}s");
                }
            }
            $output->writeln('');
        }

        // Set up progress reporter (suppress for non-table formats unless writing to file)
        $progress = null;
        if ($format === 'table' || $outputPath) {
            $installedCount = count($integration->getInstalledPackages($packageFilter));
            $progress = new ProgressReporter($output, $installedCount);
        }

        $results = $integration->checkForUpdates($progress, $packageFilter);

        if ($progress !== null) {
            $progress->finish();
        }

        // Format output
        $formatter = new OutputFormatter();
        switch ($format) {
            case 'json':
                $formatted = $formatter->formatJson($results);
                break;
            case 'csv':
                $formatted = $formatter->formatCsv($results);
                break;
            default:
                $formatted = $formatter->formatTable($results);
                break;
        }

        // Write to file or stdout
        if ($outputPath) {
            $formatter->writeToFile($formatted, $outputPath);
            if ($format === 'table') {
                $output->writeln("<info>Results written to:</info> $outputPath");
            }
        } else {
            $output->writeln($formatted);
        }

        return $this->getExitCode($results);
    }

    /**
     * Check a single vendor URL.
     *
     * @param string $url
     * @param OutputInterface $output
     * @param string $format
     * @return int
     */
    protected function checkSingleUrl($url, OutputInterface $output, $format)
    {
        $checker = new VersionChecker();

        if ($format === 'table') {
            $output->writeln("<info>Checking vendor URL:</info> $url");
            $output->writeln('');
        }

        try {
            $result = $checker->getVendorVersion($url);

            if ($format === 'json') {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
            } else {
                $this->displaySingleResult($result, $output, $output->isVerbose());
            }

            return 0;
        } catch (\Exception $e) {
            if ($format === 'json') {
                $output->writeln(json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT));
            } else {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");
            }
            return 1;
        }
    }

    /**
     * Determine exit code from results for CI/CD pipelines.
     *
     * @param array $results
     * @return int 0 = all current, 1 = updates available, 2 = errors
     */
    protected function getExitCode(array $results)
    {
        $hasErrors = false;
        $hasUpdates = false;

        foreach ($results as $result) {
            if ($result['status'] === 'ERROR') {
                $hasErrors = true;
            }
            if ($result['status'] === 'UPDATE_AVAILABLE') {
                $hasUpdates = true;
            }
        }

        if ($hasErrors) {
            return 2;
        }
        if ($hasUpdates) {
            return 1;
        }
        return 0;
    }

    /**
     * Display a single URL check result.
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
                $output->writeln("  {$entry['version']} - {$entry['date']}");
                if (!empty($entry['changes'])) {
                    foreach (array_slice($entry['changes'], 0, 3) as $change) {
                        $output->writeln("    - $change");
                    }
                }
            }
        }
    }
}
