<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Module\ModuleManifest;

/**
 * Adds a package-owned section to the discovery cache.
 *
 * Declare contributors in a module's module.php under the 'discovery' key.
 * `marko discovery:cache` resolves each one through the container, calls
 * compile() and stores the result under key(). At boot the owning package
 * reads its section back through CachedDiscovery::section().
 */
interface DiscoveryCacheContributorInterface
{
    /**
     * Unique key for this contributor's section in the cache payload, e.g. 'routes'.
     */
    public function key(): string;

    /**
     * Compute the section from live discovery.
     *
     * The result is written with var_export(), so it may contain only
     * scalars, null and arrays.
     *
     * @param array<ModuleManifest> $modules Enabled modules in load order
     * @return array<mixed>
     */
    public function compile(array $modules): array;
}
