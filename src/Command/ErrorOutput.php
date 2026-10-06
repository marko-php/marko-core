<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Output for warnings and errors, written to STDERR so they stay visible when STDOUT is piped or captured.
 *
 * Inject it into a command that must report a problem without failing; the container builds it on STDERR.
 */
class ErrorOutput extends Output
{
    /**
     * @param resource|null $stream The stream to write to (defaults to STDERR)
     */
    public function __construct(
        mixed $stream = null,
    ) {
        parent::__construct($stream ?? STDERR);
    }
}
