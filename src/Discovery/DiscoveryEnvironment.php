<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Environment\AppEnvironment;

/**
 * Boot-time reader for the discovery cache settings.
 *
 * Reads $_ENV with a getenv() fallback, so it works whether or not marko/env
 * is installed and regardless of PHP's variables_order setting.
 */
class DiscoveryEnvironment
{
    private const array FALSE_VALUES = ['0', 'false', 'no', 'off', ''];

    public function __construct(
        private readonly AppEnvironment $appEnvironment = new AppEnvironment(),
    ) {}

    public function enabled(): bool
    {
        $value = $this->read('DISCOVERY_CACHE_ENABLED');

        if ($value === null) {
            return true;
        }

        return !in_array(strtolower($value), self::FALSE_VALUES, strict: true);
    }

    public function environment(): string
    {
        return $this->appEnvironment->name();
    }

    public function cachePath(): string
    {
        return $this->read('DISCOVERY_CACHE_PATH') ?? 'storage/cache/discovery.php';
    }

    private function read(
        string $name,
    ): ?string {
        if (array_key_exists($name, $_ENV) && is_scalar($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        $value = getenv($name);

        return $value === false ? null : $value;
    }
}
