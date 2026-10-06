<?php

declare(strict_types=1);

use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Command\StdinConfirmationPrompter;

/**
 * @return resource
 */
function confirmationAnswerStream(
    string $content,
): mixed {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

/**
 * @param resource|null $outputStream
 */
function confirmationPrompter(
    string $answer,
    mixed $outputStream = null,
): StdinConfirmationPrompter {
    return new StdinConfirmationPrompter(
        input: new Input(['marko', 'test:cmd']),
        output: new Output($outputStream ?? fopen('php://memory', 'w+')),
        stream: confirmationAnswerStream($answer),
    );
}

/**
 * @param resource $stream
 */
function writtenTo(
    mixed $stream,
): string {
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('implements ConfirmationPrompterInterface', function (): void {
    expect(confirmationPrompter(''))->toBeInstanceOf(ConfirmationPrompterInterface::class);
});

it('writes the question with a [y/N] hint when the default is no', function (): void {
    $output = fopen('php://memory', 'w+');

    confirmationPrompter("y\n", $output)->confirm('Drop the table?');

    expect(writtenTo($output))->toBe('Drop the table? [y/N] ');
});

it('writes the question with a [Y/n] hint when the default is yes', function (): void {
    $output = fopen('php://memory', 'w+');

    confirmationPrompter("y\n", $output)->confirm('Install it?', default: true);

    expect(writtenTo($output))->toBe('Install it? [Y/n] ');
});

it('confirms on y or yes in any case', function (string $answer): void {
    expect(confirmationPrompter($answer)->confirm('Continue?'))->toBeTrue();
})->with(["y\n", "yes\n", "Y\n", " YES \n", 'Yes']);

it('declines on n or no in any case', function (string $answer): void {
    expect(confirmationPrompter($answer)->confirm('Continue?', default: true))->toBeFalse();
})->with(["n\n", "no\n", "N\n", " NO \n", 'No']);

it('returns the default on empty input, end of input or an unrecognised answer', function (string $answer): void {
    expect(confirmationPrompter($answer)->confirm('Continue?'))->toBeFalse()
        ->and(confirmationPrompter($answer)->confirm('Continue?', default: true))->toBeTrue();
})->with(["\n", '', "maybe\n"]);

it('is not interactive when standard input is not a terminal', function (): void {
    expect(confirmationPrompter('')->isInteractive())->toBeFalse();
});

it('is not interactive when --no-interaction is passed', function (): void {
    $prompter = new StdinConfirmationPrompter(
        input: new Input(['marko', 'test:cmd', '--no-interaction']),
        output: new Output(fopen('php://memory', 'w')),
        stream: STDIN,
    );

    expect($prompter->isInteractive())->toBeFalse();
});

it('returns the default without asking or reading when --no-interaction is passed', function (): void {
    $output = fopen('php://memory', 'w+');
    $answers = confirmationAnswerStream("y\n");
    $prompter = new StdinConfirmationPrompter(
        input: new Input(['marko', 'test:cmd', '--no-interaction']),
        output: new Output($output),
        stream: $answers,
    );

    expect($prompter->confirm('Continue?'))->toBeFalse()
        ->and($prompter->confirm('Continue?', default: true))->toBeTrue()
        ->and(writtenTo($output))->toBe('')
        ->and(ftell($answers))->toBe(0);
});
