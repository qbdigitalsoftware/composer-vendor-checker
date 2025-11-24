<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/**
 * Composer Plugin to add vendor:check command
 */
class ComposerPlugin implements PluginInterface, Capable
{
    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Plugin activation - nothing needed here as we use getCapabilities()
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // Plugin deactivation
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // Plugin uninstall
    }

    /**
     * Provide capabilities
     *
     * @return array
     */
    public function getCapabilities()
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }
}
