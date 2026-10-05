<?php

declare(strict_types=1);

namespace Marko\TestFixtureAware\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    public static function noDriverInstalled(
        ?string $interface = null,
    ): self {
        return new self(
            message: 'Unresolved interface: ' . ($interface ?? 'none'),
            context: 'Attempted to resolve a TestFixtureAware interface',
            suggestion: 'Bind it in module.php',
        );
    }
}
