<?php
/**
 * Copyright © QB Digital Software Ltd. All rights reserved.
 */

namespace QBDigital\VendorChecker;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use QBDigital\VendorChecker\Command\VendorCheckCommand;

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
    public function getCommands(): array
    {
        return [
            new VendorCheckCommand(),
        ];
    }
}
