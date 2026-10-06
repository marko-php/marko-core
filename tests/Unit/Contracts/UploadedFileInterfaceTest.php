<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Contracts;

use Marko\Core\Contracts\UploadedFileInterface;
use ReflectionClass;
use ReflectionMethod;

it('declares the uploaded file inspection methods', function (): void {
    $reflection = new ReflectionClass(UploadedFileInterface::class);
    $methods = array_map(
        fn (ReflectionMethod $method): string => $method->getName(),
        $reflection->getMethods(),
    );

    expect($reflection->isInterface())->toBeTrue()
        ->and($methods)->toEqualCanonicalizing([
            'clientFilename',
            'clientMediaType',
            'size',
            'error',
            'isValid',
            'isMoved',
            'moveTo',
            'stream',
            'contents',
            'mimeType',
            'guessExtension',
        ]);
});
