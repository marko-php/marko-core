<?php

declare(strict_types=1);

use Marko\Core\Command\ErrorOutput;
use Marko\Core\Command\Output;

it('is an Output', function (): void {
    expect(new ErrorOutput())->toBeInstanceOf(Output::class);
});

it('writes to STDERR by default', function (): void {
    $stream = new ReflectionProperty(Output::class, 'stream')->getValue(new ErrorOutput());

    expect($stream)->toBe(STDERR);
});

it('writes to the stream it is given', function (): void {
    $stream = fopen('php://memory', 'r+');
    new ErrorOutput($stream)->writeLine('Warning: drift check skipped');
    rewind($stream);

    expect(stream_get_contents($stream))->toBe("Warning: drift check skipped\n");
});
