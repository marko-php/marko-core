<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\RequestStateResetter;

function requestStateResetterSpy(
    array &$log,
    string $label,
): ResettableInterface {
    return new class ($log, $label) implements ResettableInterface
    {
        /**
         * @param array<string> $log
         */
        public function __construct(
            private array &$log,
            private readonly string $label,
        ) {}

        public function reset(): void
        {
            $this->log[] = $this->label;
        }
    };
}

describe('RequestStateResetter', function (): void {
    it('resets every resolved ResettableInterface instance', function (): void {
        $log = [];
        $container = new Container();
        $container->instance('first', requestStateResetterSpy($log, 'first'));
        $container->instance('second', requestStateResetterSpy($log, 'second'));

        new RequestStateResetter($container)->reset();

        expect($log)->toEqualCanonicalizing(['first', 'second']);
    });

    it('does not touch resolved instances that are not resettable', function (): void {
        $container = new Container();
        $plain = new stdClass();
        $plain->touched = false;
        $container->instance('plain', $plain);

        new RequestStateResetter($container)->reset();

        expect($plain->touched)->toBeFalse();
    });

    it('resets in ascending binding id order', function (): void {
        $log = [];
        $container = new Container();
        $container->instance('Zeta\Service', requestStateResetterSpy($log, 'zeta'));
        $container->instance('Alpha\Service', requestStateResetterSpy($log, 'alpha'));
        $container->instance('Mid\Service', requestStateResetterSpy($log, 'mid'));

        new RequestStateResetter($container)->reset();

        expect($log)->toBe(['alpha', 'mid', 'zeta']);
    });

    it('lets a reset failure propagate', function (): void {
        $container = new Container();
        $container->instance('faulty', new class () implements ResettableInterface
        {
            public function reset(): void
            {
                throw new RuntimeException('reset failed');
            }
        });

        new RequestStateResetter($container)->reset();
    })->throws(RuntimeException::class, 'reset failed');
});
