<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\CachedModule;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;

function modulesCacheBase(): string
{
    $base = sys_get_temp_dir() . '/marko-cache-modules-' . bin2hex(random_bytes(6));
    mkdir($base . '/vendor/composer', 0755, true);
    file_put_contents($base . '/vendor/composer/installed.json', '{"packages": []}');

    return $base;
}

function modulesCacheCleanup(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function modulesCachePayload(
    array $extra = [],
): array {
    return [
        'preferences' => [],
        'plugins' => [],
        'observers' => [],
        'commands' => [],
        ...$extra,
    ];
}

describe('DiscoveryCache modules, middleware, sections and staleness', function (): void {
    beforeEach(function (): void {
        $this->savedPath = array_key_exists('DISCOVERY_CACHE_PATH', $_ENV) ? $_ENV['DISCOVERY_CACHE_PATH'] : null;
        unset($_ENV['DISCOVERY_CACHE_PATH']);
        $this->base = modulesCacheBase();
        $this->cache = new DiscoveryCache(new ProjectPaths($this->base), new DiscoveryEnvironment());
    });

    afterEach(function (): void {
        if ($this->savedPath === null) {
            unset($_ENV['DISCOVERY_CACHE_PATH']);
        } else {
            $_ENV['DISCOVERY_CACHE_PATH'] = $this->savedPath;
        }

        modulesCacheCleanup($this->base);
    });

    it('round-trips the module list in order with paths relative to the project root', function (): void {
        $this->cache->write(modulesCachePayload([
            'modules' => [
                new ModuleManifest(
                    name: 'marko/core',
                    version: '1.0.0',
                    require: ['psr/container' => '^2.0'],
                    after: ['marko/env'],
                    before: ['marko/routing'],
                    path: $this->base . '/vendor/marko/core',
                    source: 'vendor',
                    autoload: ['Marko\\Core\\' => 'src/'],
                    extra: ['marko' => ['module' => true]],
                    globalMiddleware: ['App\\CoreMiddleware'],
                ),
                new ModuleManifest(name: 'app/blog', version: '1.0.0', path: $this->base . '/app/blog', source: 'app'),
                new ModuleManifest(
                    name: 'acme/outside',
                    version: '1.0.0',
                    path: '/opt/shared/outside',
                    source: 'modules',
                ),
            ],
        ]));

        $content = (string) file_get_contents($this->cache->path());
        $modules = $this->cache->load()['modules'];

        expect($content)->toContain("'vendor/marko/core'")
            ->and($content)->not->toContain($this->base . '/vendor/marko/core')
            ->and(array_map(fn (CachedModule $m): string => $m->name, $modules))
            ->toBe(['marko/core', 'app/blog', 'acme/outside'])
            ->and($modules[0]->path)->toBe($this->base . '/vendor/marko/core')
            ->and($modules[0]->source)->toBe('vendor')
            ->and($modules[0]->require)->toBe(['psr/container' => '^2.0'])
            ->and($modules[0]->autoload)->toBe(['Marko\\Core\\' => 'src/'])
            ->and($modules[0]->extra)->toBe(['marko' => ['module' => true]])
            ->and($modules[0]->after)->toBe(['marko/env'])
            ->and($modules[0]->before)->toBe(['marko/routing'])
            ->and($modules[0]->globalMiddleware)->toBe(['App\\CoreMiddleware'])
            ->and($modules[2]->path)->toBe('/opt/shared/outside');
    });

    it('round-trips global middleware and contributor sections', function (): void {
        $this->cache->write(modulesCachePayload([
            'globalMiddleware' => ['App\\FirstMiddleware', 'App\\SecondMiddleware'],
            'sections' => [
                'routes' => [['method' => 'GET', 'path' => '/']],
                'entities' => ['App\\Entity\\Post'],
            ],
        ]));

        $loaded = $this->cache->load();

        expect($loaded['globalMiddleware'])->toBe(['App\\FirstMiddleware', 'App\\SecondMiddleware'])
            ->and($loaded['sections'])->toBe([
                'routes' => [['method' => 'GET', 'path' => '/']],
                'entities' => ['App\\Entity\\Post'],
            ]);
    });

    it(
        'throws a stale DiscoveryCacheException when installed.json changes after the cache is written',
        function (): void {
            $this->cache->write(modulesCachePayload());

            file_put_contents($this->base . '/vendor/composer/installed.json', '{"packages": [{"name": "acme/new"}]}');

            expect(fn () => $this->cache->load())->toThrow(DiscoveryCacheException::class, 'is stale');
        },
    );

    it(
        'throws a stale DiscoveryCacheException when a module directory is added under app or modules',
        function (): void {
            $this->cache->write(modulesCachePayload());

            mkdir($this->base . '/app/admin', 0755, true);
            file_put_contents($this->base . '/app/admin/composer.json', '{"name": "app/admin"}');

            expect(fn () => $this->cache->load())->toThrow(DiscoveryCacheException::class, 'is stale');
        },
    );

    it('suggests running discovery:cache after deploying when the cache is stale', function (): void {
        $this->cache->write(modulesCachePayload());
        unlink($this->base . '/vendor/composer/installed.json');

        try {
            $this->cache->load();
            $this->fail('Expected a stale DiscoveryCacheException');
        } catch (DiscoveryCacheException $e) {
            expect($e->getSuggestion())->toContain('marko discovery:cache');
        }
    });

    it('throws versionMismatch for a version 2 cache file', function (): void {
        $path = $this->cache->path();
        mkdir(dirname($path), 0755, true);
        file_put_contents(
            $path,
            "<?php return ['version' => 2, 'preferences' => [], 'plugins' => [], 'observers' => [], 'commands' => []];",
        );

        expect(fn () => $this->cache->load())->toThrow(
            DiscoveryCacheException::class,
            'found 2, expected ' . DiscoveryCache::CACHE_VERSION,
        );
    });

    it('throws malformed when a module record or section is invalid', function (): void {
        $path = $this->cache->path();
        mkdir(dirname($path), 0755, true);
        $version = DiscoveryCache::CACHE_VERSION;
        $base = "'version' => $version, 'fingerprint' => 'x', 'preferences' => [], 'plugins' => [], 'observers' => [], 'commands' => []";

        file_put_contents(
            $path,
            "<?php return [$base, 'globalMiddleware' => [], 'modules' => [['name' => 'a/b']], 'sections' => []];",
        );
        expect(fn () => $this->cache->load())
            ->toThrow(DiscoveryCacheException::class, "modules[0] missing required field 'version'");

        file_put_contents(
            $path,
            "<?php return [$base, 'globalMiddleware' => [], 'modules' => [], 'sections' => ['routes' => 'nope']];",
        );
        expect(fn () => $this->cache->load())
            ->toThrow(DiscoveryCacheException::class, 'sections.routes must be an array');

        file_put_contents(
            $path,
            "<?php return [$base, 'globalMiddleware' => [1], 'modules' => [], 'sections' => []];",
        );
        expect(fn () => $this->cache->load())
            ->toThrow(DiscoveryCacheException::class, 'globalMiddleware[0] must be a string');

        file_put_contents($path, "<?php return [$base, 'globalMiddleware' => [], 'sections' => []];");
        expect(fn () => $this->cache->load())
            ->toThrow(DiscoveryCacheException::class, "missing required key 'modules'");
    });
});
