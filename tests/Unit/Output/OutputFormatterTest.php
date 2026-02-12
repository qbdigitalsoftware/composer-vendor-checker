<?php

namespace GetJohn\VendorChecker\Tests\Unit\Output;

use GetJohn\VendorChecker\Output\OutputFormatter;
use PHPUnit\Framework\TestCase;

class OutputFormatterTest extends TestCase
{
    /** @var OutputFormatter */
    private $formatter;

    /** @var array */
    private $sampleResults;

    protected function setUp(): void
    {
        $this->formatter = new OutputFormatter();
        $this->sampleResults = [
            [
                'package' => 'amasty/promo',
                'installed_version' => '2.12.0',
                'latest_version' => '2.14.0',
                'status' => 'UPDATE_AVAILABLE',
                'source' => 'packagist',
            ],
            [
                'package' => 'klaviyo/magento2-extension',
                'installed_version' => '4.4.2',
                'latest_version' => '4.4.2',
                'status' => 'UP_TO_DATE',
                'source' => 'packagist',
            ],
            [
                'package' => 'xtento/orderexport',
                'installed_version' => '2.14.9',
                'latest_version' => 'Error',
                'status' => 'ERROR',
                'error' => 'Connection timeout',
            ],
        ];
    }

    public function testFormatTableContainsSummary()
    {
        $output = $this->formatter->formatTable($this->sampleResults);

        $this->assertStringContainsString('1 up-to-date', $output);
        $this->assertStringContainsString('1 updates available', $output);
        $this->assertStringContainsString('1 errors', $output);
    }

    public function testFormatTableContainsPackageNames()
    {
        $output = $this->formatter->formatTable($this->sampleResults);

        $this->assertStringContainsString('amasty/promo', $output);
        $this->assertStringContainsString('klaviyo/magento2-extension', $output);
        $this->assertStringContainsString('xtento/orderexport', $output);
    }

    public function testFormatTableShowsSourceLabels()
    {
        $output = $this->formatter->formatTable($this->sampleResults);
        $this->assertStringContainsString('[via Packagist]', $output);
    }

    public function testFormatTableShowsErrorMessages()
    {
        $output = $this->formatter->formatTable($this->sampleResults);
        $this->assertStringContainsString('Connection timeout', $output);
    }

    public function testFormatJsonIsValidJson()
    {
        $json = $this->formatter->formatJson($this->sampleResults);
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertCount(3, $decoded);
    }

    public function testFormatJsonPreservesStructure()
    {
        $json = $this->formatter->formatJson($this->sampleResults);
        $decoded = json_decode($json, true);

        $this->assertEquals('amasty/promo', $decoded[0]['package']);
        $this->assertEquals('UPDATE_AVAILABLE', $decoded[0]['status']);
    }

    public function testFormatCsvHasHeaderRow()
    {
        $csv = $this->formatter->formatCsv($this->sampleResults);
        $lines = explode("\n", trim($csv));

        $this->assertEquals('Package,Installed Version,Latest Version,Status,Source,Error', $lines[0]);
    }

    public function testFormatCsvHasCorrectRowCount()
    {
        $csv = $this->formatter->formatCsv($this->sampleResults);
        $lines = explode("\n", trim($csv));

        // Header + 3 data rows
        $this->assertCount(4, $lines);
    }

    public function testFormatCsvEscapesCommas()
    {
        $results = [
            [
                'package' => 'test/pkg',
                'installed_version' => '1.0.0',
                'latest_version' => '2.0.0',
                'status' => 'ERROR',
                'source' => '',
                'error' => 'Error with, comma in message',
            ],
        ];

        $csv = $this->formatter->formatCsv($results);
        $this->assertStringContainsString('"Error with, comma in message"', $csv);
    }

    public function testWriteToFileCreatesFile()
    {
        $tempFile = sys_get_temp_dir() . '/vendor-check-test-output-' . uniqid() . '.json';

        try {
            $this->formatter->writeToFile('{"test": true}', $tempFile);
            $this->assertFileExists($tempFile);
            $this->assertEquals('{"test": true}', file_get_contents($tempFile));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFormatTableWithEmptyResults()
    {
        $output = $this->formatter->formatTable([]);
        $this->assertStringContainsString('0 up-to-date', $output);
        $this->assertStringContainsString('0 errors', $output);
    }
}
