<?php

namespace GetJohn\VendorChecker\Tests\Unit\Command;

use GetJohn\VendorChecker\Command\VendorCheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class VendorCheckCommandTest extends TestCase
{
    /** @var VendorCheckCommand */
    private $command;

    /** @var CommandTester */
    private $tester;

    protected function setUp(): void
    {
        $this->command = new VendorCheckCommand();
        $this->command->setName('vendor:check');

        $app = new Application();
        $app->add($this->command);

        $this->tester = new CommandTester($app->find('vendor:check'));
    }

    public function testCommandName()
    {
        $this->assertEquals('vendor:check', $this->command->getName());
    }

    public function testCommandDescription()
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    public function testHasFormatOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('format'));
        $this->assertEquals('table', $definition->getOption('format')->getDefault());
    }

    public function testHasOutputOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('output'));
    }

    public function testHasNoCacheOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('no-cache'));
    }

    public function testHasClearCacheOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('clear-cache'));
    }

    public function testHasCacheTtlOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('cache-ttl'));
        $this->assertEquals(3600, $definition->getOption('cache-ttl')->getDefault());
    }

    public function testHasConfigOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('config'));
    }

    public function testHasJsonOption()
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('json'));
    }

    public function testExitCodeMapping()
    {
        // Use reflection to test getExitCode directly
        $method = new \ReflectionMethod(VendorCheckCommand::class, 'getExitCode');
        $method->setAccessible(true);

        // All up to date
        $this->assertEquals(0, $method->invoke($this->command, [
            ['status' => 'UP_TO_DATE'],
            ['status' => 'UP_TO_DATE'],
        ]));

        // Has updates
        $this->assertEquals(1, $method->invoke($this->command, [
            ['status' => 'UP_TO_DATE'],
            ['status' => 'UPDATE_AVAILABLE'],
        ]));

        // Has errors (takes priority over updates)
        $this->assertEquals(2, $method->invoke($this->command, [
            ['status' => 'UPDATE_AVAILABLE'],
            ['status' => 'ERROR'],
        ]));

        // Empty results
        $this->assertEquals(0, $method->invoke($this->command, []));
    }
}
