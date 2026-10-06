<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

/**
 * A class file that discovery skipped because it references a Marko class
 * from a package that is not installed.
 */
readonly class DiscoverySkip
{
    public function __construct(
        public string $filePath,
        public string $className,
        public string $missingClass,
        public string $missingPackage,
    ) {}

    public function message(): string
    {
        return "Discovery skipped $this->className ($this->filePath): it references $this->missingClass, "
            . "which is not available. Install $this->missingPackage if this class should be active, "
            . 'or fix the reference if it is a typo.';
    }
}
