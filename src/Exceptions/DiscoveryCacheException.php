<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;

class DiscoveryCacheException extends MarkoException
{
    public static function unreadable(string $path): self
    {
        return new self(
            message: "Discovery cache file '$path' could not be read",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function malformed(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Discovery cache file '$path' is malformed: $reason",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function versionMismatch(
        string $path,
        int $found,
        int $expected,
    ): self {
        return new self(
            message: "Discovery cache version mismatch in '$path': found $found, expected $expected",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function stale(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Discovery cache file '$path' is stale: $reason",
            context: "While loading the discovery cache from '$path'",
            suggestion: 'Run `marko discovery:cache` after every deploy (it is a required deploy step), or `marko discovery:clear` to fall back to live discovery',
        );
    }

    public static function missingSection(
        string $path,
        string $key,
    ): self {
        return new self(
            message: "Discovery cache file '$path' has no '$key' section",
            context: "While reading the '$key' section of the discovery cache",
            suggestion: 'Run `marko discovery:cache` to recompile the cache with every installed contributor',
        );
    }

    public static function invalidContributor(
        string $moduleName,
        string $className,
        string $reason,
    ): self {
        return new self(
            message: "Module '$moduleName' declares an invalid discovery contributor '$className': $reason",
            context: "While compiling the discovery cache contributors declared in the 'discovery' key of $moduleName's module.php",
            suggestion: 'List only existing classes that implement ' . DiscoveryCacheContributorInterface::class,
        );
    }

    public static function duplicateContributorKey(
        string $key,
        string $firstClass,
        string $secondClass,
    ): self {
        return new self(
            message: "Discovery contributors '$firstClass' and '$secondClass' both use the section key '$key'",
            context: 'While compiling the discovery cache',
            suggestion: 'Give each discovery contributor a unique key()',
        );
    }

    public static function unexportableSection(
        string $key,
        string $className,
        string $reason,
    ): self {
        return new self(
            message: "Discovery contributor '$className' returned data for '$key' that cannot be cached: $reason",
            context: 'While compiling the discovery cache',
            suggestion: 'Return only scalars, null and arrays from compile() and rebuild objects when hydrating the section',
        );
    }

    public static function notWritable(string $path): self
    {
        return new self(
            message: "Discovery cache file '$path' could not be written",
            context: "While writing the discovery cache to '$path'",
            suggestion: "Ensure the directory containing '$path' is writable by the web server or CLI user",
        );
    }
}
