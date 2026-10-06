<?php

declare(strict_types=1);

namespace Marko\Core\Support;

/**
 * Runs a PHP call that reports failure through a warning (mkdir, rename,
 * stream_socket_client, unserialize, ...) and captures the warning's message
 * instead of emitting it, so the caller can fold the reason into a loud
 * exception.
 *
 * Use this instead of the `@` operator: `@` hides the only part of a failure
 * that says why, and error_get_last() can return a stale, unrelated error.
 * The handler is scoped to the call and is always restored, even when the
 * operation throws.
 *
 * The handler captures every error level the operation raises (warnings,
 * notices and deprecations alike), so wrap only the single call whose failure
 * you are reporting, never a larger block.
 *
 *     if (!ErrorCapture::run($reason, fn (): bool => rename($from, $to))) {
 *         throw SomeException::renameFailed($to, $reason);
 *     }
 */
class ErrorCapture
{
    /**
     * @template T
     *
     * @param ?string $reason Set to the captured message(s), joined with '; ', or null when nothing was raised
     * @param callable(): T $operation
     *
     * @return T
     *
     * @param-out ?string $reason
     */
    public static function run(
        ?string &$reason,
        callable $operation,
    ): mixed {
        $reason = null;
        $messages = [];

        set_error_handler(function (int $errno, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        });

        try {
            return $operation();
        } finally {
            restore_error_handler();

            if ($messages !== []) {
                $reason = implode('; ', $messages);
            }
        }
    }
}
