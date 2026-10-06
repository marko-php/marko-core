<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;

describe('CachedDiscovery', function (): void {
    it('returns null for every section when not booted from the cache', function (): void {
        $cachedDiscovery = new CachedDiscovery();

        expect($cachedDiscovery->isCached())->toBeFalse()
            ->and($cachedDiscovery->section('routes'))->toBeNull();
    });

    it('returns a cached section by key', function (): void {
        $cachedDiscovery = new CachedDiscovery(['routes' => [['GET', '/']]], '/tmp/discovery.php');

        expect($cachedDiscovery->isCached())->toBeTrue()
            ->and($cachedDiscovery->section('routes'))->toBe([['GET', '/']]);
    });

    it('throws missingSection when a cached boot asks for an absent section', function (): void {
        $cachedDiscovery = new CachedDiscovery([], '/tmp/discovery.php');

        expect(fn () => $cachedDiscovery->section('entities'))
            ->toThrow(DiscoveryCacheException::class, "Discovery cache file '/tmp/discovery.php' has no 'entities' section");
    });

    it('autowires an uncached CachedDiscovery when constructed with no arguments', function (): void {
        $cachedDiscovery = new Container()->get(CachedDiscovery::class);

        expect($cachedDiscovery->isCached())->toBeFalse();
    });
});
