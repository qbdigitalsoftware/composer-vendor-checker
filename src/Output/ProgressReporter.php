<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Output;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports per-package progress to the console.
 * Outputs: [12/85] amasty/promo ... packagist OK
 */
class ProgressReporter
{
    /** @var OutputInterface */
    private $output;

    /** @var int Total packages to check */
    private $total;

    /** @var int Current progress counter */
    private $current = 0;

    /**
     * @param OutputInterface $output
     * @param int $total Total number of packages to check
     */
    public function __construct(OutputInterface $output, $total)
    {
        $this->output = $output;
        $this->total = $total;
    }

    /**
     * Report progress on a single package check.
     *
     * @param string $packageName
     * @param string $method Check method used (packagist, private_repo, website, cached)
     * @param string|null $status Result status (UP_TO_DATE, UPDATE_AVAILABLE, ERROR, etc.)
     */
    public function advance($packageName, $method, $status = null)
    {
        $this->current++;

        $pad = strlen((string)$this->total);
        $counter = sprintf("[%{$pad}d/%d]", $this->current, $this->total);

        $statusIcon = '';
        if ($status !== null) {
            switch ($status) {
                case 'UP_TO_DATE':
                    $statusIcon = '<info>OK</info>';
                    break;
                case 'UPDATE_AVAILABLE':
                    $statusIcon = '<comment>UPDATE</comment>';
                    break;
                case 'ERROR':
                case 'UNAVAILABLE':
                    $statusIcon = '<error>ERR</error>';
                    break;
                case 'AHEAD_OF_VENDOR':
                    $statusIcon = '<comment>AHEAD</comment>';
                    break;
                default:
                    $statusIcon = $status;
            }
        }

        $line = sprintf(
            "  %s %-50s %s %s",
            $counter,
            $packageName,
            $method,
            $statusIcon
        );

        $this->output->writeln(rtrim($line));
    }

    /**
     * Output a completion message.
     */
    public function finish()
    {
        $this->output->writeln('');
    }
}
