<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Exceptions\DiscoveryCacheException;

/**
 * The contributor sections of the discovery cache for this boot.
 *
 * Application binds one instance on every boot. On a live boot (no cache, a
 * development environment, or the cache disabled) it is uncached and
 * section() returns null, so the owning package runs its own discovery. On a
 * cached boot section() returns the data its DiscoveryCacheContributorInterface
 * compiled.
 */
readonly class CachedDiscovery
{
    /**
     * @param array<string, array<mixed>>|null $sections Sections keyed by contributor key; null when not booted from the cache
     * @param string $path Cache file path, for error messages
     */
    public function __construct(
        private ?array $sections = null,
        private string $path = '',
    ) {}

    public function isCached(): bool
    {
        return $this->sections !== null;
    }

    /**
     * A contributor's cached section, or null when this boot did not use the cache.
     *
     * @return array<mixed>|null
     *
     * @throws DiscoveryCacheException When booted from the cache but the section is missing
     */
    public function section(
        string $key,
    ): ?array {
        if ($this->sections === null) {
            return null;
        }

        return $this->sections[$key] ?? throw DiscoveryCacheException::missingSection($this->path, $key);
    }
}
