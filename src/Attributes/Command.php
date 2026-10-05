<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Command
{
    /**
     * @param list<string> $aliases
     * @param list<string> $flags Value-less options (e.g. `force`, `d`) that never consume the next token
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public array $flags = [],
    ) {}
}
