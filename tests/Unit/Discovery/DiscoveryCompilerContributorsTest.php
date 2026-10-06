<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;

class CompilerTestGreeting
{
    public function __construct(
        public string $text = 'hello',
    ) {}
}

class CompilerTestRoutesContributor implements DiscoveryCacheContributorInterface
{
    public function __construct(
        private CompilerTestGreeting $greeting,
    ) {}

    public function key(): string
    {
        return 'routes';
    }

    public function compile(array $modules): array
    {
        return [
            'greeting' => $this->greeting->text,
            'modules' => array_map(fn (ModuleManifest $m): string => $m->name, $modules),
        ];
    }
}

class CompilerTestEntitiesContributor implements DiscoveryCacheContributorInterface
{
    public function key(): string
    {
        return 'entities';
    }

    public function compile(array $modules): array
    {
        return ['App\\Entity\\Post'];
    }
}

class CompilerTestDuplicateRoutesContributor extends CompilerTestEntitiesContributor
{
    public function key(): string
    {
        return 'routes';
    }
}

class CompilerTestObjectContributor extends CompilerTestEntitiesContributor
{
    public function key(): string
    {
        return 'objects';
    }

    public function compile(array $modules): array
    {
        return ['nested' => [new stdClass()]];
    }
}

/**
 * @param array<int, string> $discovery
 */
function compilerContributorModule(string $name, array $discovery = []): ModuleManifest
{
    return new ModuleManifest(
        name: $name,
        version: '1.0.0',
        path: sys_get_temp_dir() . '/marko-compiler-contributors-missing-' . $name,
        discovery: $discovery,
    );
}

describe('DiscoveryCompiler contributors', function (): void {
    it('stores each contributor result under its key', function (): void {
        $payload = new DiscoveryCompiler(new Container())->compile([
            compilerContributorModule('acme/routing', [CompilerTestRoutesContributor::class]),
            compilerContributorModule('acme/database', [CompilerTestEntitiesContributor::class]),
        ]);

        expect(array_keys($payload['sections']))->toBe(['routes', 'entities'])
            ->and($payload['sections']['entities'])->toBe(['App\\Entity\\Post'])
            ->and($payload['sections']['routes']['modules'])->toBe(['acme/routing', 'acme/database']);
    });

    it('resolves contributors through the container', function (): void {
        $container = new Container();
        $container->instance(CompilerTestGreeting::class, new CompilerTestGreeting('from the container'));

        $payload = new DiscoveryCompiler($container)->compile([
            compilerContributorModule('acme/routing', [CompilerTestRoutesContributor::class]),
        ]);

        expect($payload['sections']['routes']['greeting'])->toBe('from the container');
    });

    it('throws when a declared contributor does not implement DiscoveryCacheContributorInterface', function (): void {
        $compiler = new DiscoveryCompiler(new Container());

        expect(fn () => $compiler->compile([compilerContributorModule('acme/bad', [CompilerTestGreeting::class])]))
            ->toThrow(DiscoveryCacheException::class, "Module 'acme/bad' declares an invalid discovery contributor")
            ->and(fn () => $compiler->compile([compilerContributorModule('acme/bad', ['Acme\\Missing'])]))
            ->toThrow(DiscoveryCacheException::class, 'does not exist');
    });

    it('throws when two contributors use the same key', function (): void {
        $compiler = new DiscoveryCompiler(new Container());

        expect(fn () => $compiler->compile([
            compilerContributorModule('acme/routing', [CompilerTestRoutesContributor::class]),
            compilerContributorModule('acme/other', [CompilerTestDuplicateRoutesContributor::class]),
        ]))->toThrow(DiscoveryCacheException::class, "both use the section key 'routes'");
    });

    it('throws when a contributor returns data that cannot be exported', function (): void {
        $compiler = new DiscoveryCompiler(new Container());

        expect(fn () => $compiler->compile([
            compilerContributorModule('acme/objects', [CompilerTestObjectContributor::class]),
        ]))->toThrow(DiscoveryCacheException::class, 'objects.nested.0 is a stdClass');
    });

    it('includes the module list and global middleware order in the payload', function (): void {
        $modules = [compilerContributorModule('acme/one'), compilerContributorModule('acme/two')];

        $payload = new DiscoveryCompiler(new Container())->compile($modules);

        expect($payload['modules'])->toBe($modules)
            ->and($payload['globalMiddleware'])->toBe([])
            ->and($payload['sections'])->toBe([]);
    });
});
