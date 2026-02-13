<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker\Output;

/**
 * Formats version check results into various output formats.
 */
class OutputFormatter
{
    /**
     * Format results as a table (box-drawing report).
     *
     * @param array $results
     * @return string
     */
    public function formatTable(array $results)
    {
        $report = [];
        $report[] = "";
        $report[] = "  Vendor Version Check Report";
        $report[] = "  " . str_repeat("─", 74);
        $report[] = "";

        $updateCount = 0;
        $upToDateCount = 0;
        $errorCount = 0;
        $unavailableCount = 0;
        $aheadCount = 0;

        foreach ($results as $result) {
            $package = $result['package'];
            $installed = $result['installed_version'];
            $latest = $result['latest_version'];
            $status = $result['status'];

            $statusSymbol = '?';
            switch ($status) {
                case 'UP_TO_DATE':
                    $statusSymbol = '✓';
                    $upToDateCount++;
                    break;
                case 'UPDATE_AVAILABLE':
                    $statusSymbol = '↑';
                    $updateCount++;
                    break;
                case 'AHEAD_OF_VENDOR':
                    $statusSymbol = '⚠';
                    $aheadCount++;
                    break;
                case 'UNAVAILABLE':
                    $statusSymbol = '?';
                    $unavailableCount++;
                    break;
                case 'ERROR':
                    $statusSymbol = '✗';
                    $errorCount++;
                    break;
            }

            $source = isset($result['source']) ? $result['source'] : '';
            $sourceLabels = [
                'packagist' => ' [via Packagist]',
                'private_repo' => ' [via Private Repo]',
            ];
            $sourceLabel = isset($sourceLabels[$source]) ? $sourceLabels[$source] : '';

            $report[] = sprintf("  %s  %-50s", $statusSymbol, $package);
            $report[] = sprintf("      Installed: %-20s  Latest: %s%s", $installed, $latest, $sourceLabel);

            if (isset($result['recent_changes'])) {
                $report[] = "      Recent changes:";
                foreach ($result['recent_changes'] as $change) {
                    $report[] = sprintf("        • %s - %s", $change['version'], $change['date']);
                }
            }

            if (isset($result['error'])) {
                $report[] = "      Error: " . $result['error'];
            }

            $report[] = "";
        }

        $report[] = "  " . str_repeat("─", 74);
        $report[] = sprintf(
            "  Summary: %d up-to-date, %d updates available, %d ahead, %d unavailable, %d errors",
            $upToDateCount,
            $updateCount,
            $aheadCount,
            $unavailableCount,
            $errorCount
        );
        $report[] = "";

        return implode("\n", $report);
    }

    /**
     * Format results as JSON.
     *
     * @param array $results
     * @return string
     */
    public function formatJson(array $results)
    {
        return json_encode($results, JSON_PRETTY_PRINT);
    }

    /**
     * Format results as CSV with header row.
     *
     * @param array $results
     * @return string
     */
    public function formatCsv(array $results)
    {
        $lines = [];
        $lines[] = 'Package,Installed Version,Latest Version,Status,Source,Error';

        foreach ($results as $result) {
            $fields = [
                $result['package'],
                $result['installed_version'],
                $result['latest_version'],
                $result['status'],
                isset($result['source']) ? $result['source'] : '',
                isset($result['error']) ? $result['error'] : '',
            ];

            // CSV-escape each field
            $escaped = array_map(function ($field) {
                if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $fields);

            $lines[] = implode(',', $escaped);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Write formatted content to a file.
     *
     * @param string $content
     * @param string $filePath
     * @throws \RuntimeException
     */
    public function writeToFile($content, $filePath)
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = file_put_contents($filePath, $content);
        if ($written === false) {
            throw new \RuntimeException("Failed to write to: {$filePath}");
        }
    }
}
