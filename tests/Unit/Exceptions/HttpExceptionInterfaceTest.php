<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;

it('defines HttpExceptionInterface extending Throwable with status, headers and response data', function (): void {
    $reflection = new ReflectionClass(HttpExceptionInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->isSubclassOf(Throwable::class))->toBeTrue()
        ->and($reflection->getMethod('getStatusCode')->getReturnType()?->__toString())->toBe('int')
        ->and($reflection->getMethod('getHeaders')->getReturnType()?->__toString())->toBe('array')
        ->and($reflection->getMethod('getResponseData')->getReturnType()?->__toString())->toBe('array');
});
