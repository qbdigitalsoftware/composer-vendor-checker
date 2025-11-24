<?php
/**
 * Copyright © GetJohn. All rights reserved.
 */

namespace GetJohn\VendorChecker;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use GetJohn\VendorChecker\Command\VendorCheckCommand;

/**
 * Command Provider for vendor:check command
 */
class CommandProvider implements CommandProviderCapability
{
    /**
     * Get commands provided by this plugin
     *
     * @return \Composer\Command\BaseCommand[]
     */
    public function getCommands()
    {
        return [
            new VendorCheckCommand(),
        ];
    }
}
