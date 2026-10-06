<?php

declare(strict_types=1);

use Marko\Core\Support\ErrorCapture;

describe('ErrorCapture', function (): void {
    it('returns the operation result and leaves the reason null when nothing is raised', function (): void {
        $result = ErrorCapture::run($reason, fn (): int => 42);

        expect($result)->toBe(42)
            ->and($reason)->toBeNull();
    });

    it('captures the warning message raised by the operation instead of emitting it', function (): void {
        $outer = [];
        set_error_handler(function (int $errno, string $message) use (&$outer): bool {
            $outer[] = $message;

            return true;
        });

        try {
            $result = ErrorCapture::run($reason, function (): bool {
                trigger_error('disk on fire', E_USER_WARNING);

                return false;
            });
        } finally {
            restore_error_handler();
        }

        expect($result)->toBeFalse()
            ->and($reason)->toBe('disk on fire')
            ->and($outer)->toBeEmpty();
    });

    it('joins several warnings raised by one operation in order', function (): void {
        ErrorCapture::run($reason, function (): void {
            trigger_error('first', E_USER_WARNING);
            trigger_error('second', E_USER_NOTICE);
        });

        expect($reason)->toBe('first; second');
    });

    it('resets a reason left over from an earlier call', function (): void {
        $reason = 'stale';

        ErrorCapture::run($reason, fn (): bool => true);

        expect($reason)->toBeNull();
    });

    it('restores the previous error handler after the operation returns', function (): void {
        $previous = fn (): bool => true;
        set_error_handler($previous);

        try {
            ErrorCapture::run($reason, fn (): bool => true);
            $current = set_error_handler(fn (): bool => true);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        expect($current)->toBe($previous);
    });

    it('restores the previous error handler when the operation throws', function (): void {
        $previous = fn (): bool => true;
        set_error_handler($previous);

        try {
            try {
                ErrorCapture::run($reason, function (): never {
                    throw new RuntimeException('boom');
                });
            } catch (RuntimeException) {
                // expected
            }

            $current = set_error_handler(fn (): bool => true);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        expect($current)->toBe($previous);
    });

    it('captures a warning that the operation itself suppresses with @', function (): void {
        $path = sys_get_temp_dir() . '/marko-error-capture-missing-' . bin2hex(random_bytes(6)) . '/file';

        $result = ErrorCapture::run($reason, fn (): string|false => @file_get_contents($path));

        expect($result)->toBeFalse()
            ->and($reason)->toContain('No such file or directory');
    });
});
