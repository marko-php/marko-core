<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryEnvironment;

// Mirrors the boot gate. DiscoveryEnvironment is the single parser for these variables
// (marko/core cannot depend on marko/config), so an invalid value fails here as it does at boot.
$discoveryEnvironment = new DiscoveryEnvironment();

return [
    'enabled' => $discoveryEnvironment->enabled(),
    'environment' => $discoveryEnvironment->environment(),
    'cache_path' => $discoveryEnvironment->cachePath(),
];
