<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Parses and provides access to command-line arguments.
 *
 * The raw argv is parsed once, at construction, into positional arguments and options:
 *
 * - `--name=value` and `-n=value` always carry a value.
 * - `--name value` and `-n value` take the next token as the value when it does not start
 *   with `-`, unless `name` is a declared flag. Declared flags never consume the next token.
 * - `--name` / `-n` followed by another option, by `--` or by the end of input is `'true'`.
 * - `--` ends option parsing; every token after it is positional.
 * - Options can repeat (`--queue a --queue b`); `getOption()` returns the last value and
 *   `getOptionValues()` returns them all.
 *
 * Single-character names refer to short options (`-d`), longer names to long options (`--detach`).
 */
readonly class Input
{
    private const string OPTION_TERMINATOR = '--';

    /**
     * @var list<string>
     */
    private array $positionals;

    /**
     * Option values keyed by their prefixed form (`-d`, `--queue`).
     *
     * @var array<string, list<string>>
     */
    private array $options;

    /**
     * @param array<int, string> $arguments The raw command-line arguments (argv-style)
     * @param list<string> $flags Option names that never take a value (e.g. `force`, `d`)
     */
    public function __construct(
        private array $arguments,
        private array $flags = [],
    ) {
        $positionals = [];
        $options = [];
        $tokens = array_slice($this->arguments, 2);
        $count = count($tokens);
        $optionsEnded = false;

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($optionsEnded || !$this->isOptionToken($token)) {
                $positionals[] = $token;

                continue;
            }

            if ($token === self::OPTION_TERMINATOR) {
                $optionsEnded = true;

                continue;
            }

            $equalsPosition = strpos($token, '=');

            if ($equalsPosition !== false) {
                $options[substr($token, 0, $equalsPosition)][] = substr($token, $equalsPosition + 1);

                continue;
            }

            $next = $tokens[$index + 1] ?? null;

            if ($next !== null && !$this->isFlag($token) && !str_starts_with($next, '-')) {
                $options[$token][] = $next;
                $index++;

                continue;
            }

            $options[$token][] = 'true';
        }

        $this->positionals = $positionals;
        $this->options = $options;
    }

    /**
     * Returns a copy of this input re-parsed with the given value-less flags declared.
     *
     * @param list<string> $flags
     */
    public function withFlags(
        array $flags,
    ): self {
        return new self($this->arguments, $flags);
    }

    /**
     * Returns the command name (second argument after script name).
     */
    public function getCommand(): ?string
    {
        return $this->arguments[1] ?? null;
    }

    /**
     * Returns the positional arguments after the command name. Option tokens and their values are excluded.
     *
     * @return list<string>
     */
    public function getArguments(): array
    {
        return $this->positionals;
    }

    /**
     * Checks if a positional argument exists at the given index.
     */
    public function hasArgument(
        int $index,
    ): bool {
        return isset($this->positionals[$index]);
    }

    /**
     * Returns the positional argument at the given index, or null if not found.
     */
    public function getArgument(
        int $index,
    ): ?string {
        return $this->positionals[$index] ?? null;
    }

    /**
     * Checks if an option exists (e.g., --verbose, --queue=value, --queue value, -d, -p=8000).
     *
     * Single-char names match short options (-x), multi-char names match long options (--name).
     */
    public function hasOption(
        string $name,
    ): bool {
        return isset($this->options[$this->optionKey($name)]);
    }

    /**
     * Returns the value of an option, or null if not found.
     * For boolean flags (--verbose, -d), returns "true".
     * For value options (--queue=emails, --queue emails, -p=8000, -p 8000), returns the value.
     * For repeated options, returns the last value.
     *
     * Single-char names match short options, multi-char names match long options.
     */
    public function getOption(
        string $name,
    ): ?string {
        $values = $this->getOptionValues($name);

        return $values === [] ? null : $values[array_key_last($values)];
    }

    /**
     * Returns every value given for an option, in order, or an empty list if not found.
     *
     * @return list<string>
     */
    public function getOptionValues(
        string $name,
    ): array {
        return $this->options[$this->optionKey($name)] ?? [];
    }

    private function optionKey(
        string $name,
    ): string {
        return strlen($name) === 1 ? "-$name" : "--$name";
    }

    private function isOptionToken(
        string $token,
    ): bool {
        return str_starts_with($token, '-') && $token !== '-';
    }

    private function isFlag(
        string $token,
    ): bool {
        return array_any($this->flags, fn (string $flag): bool => $this->optionKey($flag) === $token);
    }
}
