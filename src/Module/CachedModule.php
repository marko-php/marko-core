<?php

declare(strict_types=1);

namespace Marko\Core\Module;

/**
 * The composer.json-derived part of a module manifest, as stored in the
 * discovery cache. ManifestParser::parseCached() combines it with the
 * module's live module.php to rebuild the full ModuleManifest.
 *
 * It also snapshots the module.php fields the cache was ordered and
 * resolved from (sequence and globalMiddleware), so a cached boot can
 * detect that module.php changed since the cache was compiled.
 */
readonly class CachedModule
{
    /**
     * @param array<string, string> $require Marko-relevant requirements (platform packages already removed)
     * @param array<string, string> $autoload PSR-4 autoload map (namespace => path)
     * @param array<string, mixed> $extra Raw composer.json extra data
     * @param array<int, string> $after module.php sequence 'after' when the cache was compiled
     * @param array<int, string> $before module.php sequence 'before' when the cache was compiled
     * @param array<int, string> $globalMiddleware module.php globalMiddleware when the cache was compiled
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $path,
        public string $source,
        public array $require = [],
        public array $autoload = [],
        public array $extra = [],
        public array $after = [],
        public array $before = [],
        public array $globalMiddleware = [],
    ) {}

    /**
     * Whether a manifest rebuilt from the live module.php still matches what
     * module ordering and global middleware were compiled from.
     */
    public function matchesLiveManifest(
        ModuleManifest $manifest,
    ): bool {
        return $manifest->enabled
            && $manifest->after === $this->after
            && $manifest->before === $this->before
            && $manifest->globalMiddleware === $this->globalMiddleware;
    }

    public static function fromManifest(
        ModuleManifest $manifest,
    ): self {
        return new self(
            name: $manifest->name,
            version: $manifest->version,
            path: $manifest->path,
            source: $manifest->source,
            require: $manifest->require,
            autoload: $manifest->autoload,
            extra: $manifest->extra,
            after: $manifest->after,
            before: $manifest->before,
            globalMiddleware: $manifest->globalMiddleware,
        );
    }
}
