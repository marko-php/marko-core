<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

/**
 * Process-wide record of every class file discovery skipped because it
 * references a class from an uninstalled Marko package.
 *
 * Skipping is intentional (optional-package integrations), but a typo or a
 * missing dependency in a Preference or Plugin would otherwise make it vanish
 * without a trace. Every ClassFileParser records here, including the parsers
 * discovery classes construct internally, so tooling such as `discovery:cache`
 * can report the full list.
 */
class DiscoverySkips
{
    /** @var array<string, DiscoverySkip> keyed by file path */
    private static array $skips = [];

    /**
     * Record a skip. Returns false when the file was already recorded.
     */
    public static function record(
        DiscoverySkip $skip,
    ): bool {
        if (isset(self::$skips[$skip->filePath])) {
            return false;
        }

        self::$skips[$skip->filePath] = $skip;

        return true;
    }

    /**
     * @return list<DiscoverySkip>
     */
    public static function all(): array
    {
        return array_values(self::$skips);
    }

    public static function clear(): void
    {
        self::$skips = [];
    }
}
