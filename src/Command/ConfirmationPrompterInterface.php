<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Asks the person running a command a yes/no question.
 *
 * The prompter writes the question and a hint (`[y/N]` or `[Y/n]`) itself. "y"/"yes" confirm and
 * "n"/"no" decline, in any case; empty input, end of input or any other answer returns the default.
 * When --no-interaction is passed, nothing is asked and the default is returned.
 *
 * A command that must not go ahead without a person's answer (a destructive action) checks
 * isInteractive() first and refuses loudly, offering a flag such as --force, rather than
 * relying on the default.
 */
interface ConfirmationPrompterInterface
{
    /**
     * Whether a person can answer: --no-interaction was not passed and input is a terminal.
     */
    public function isInteractive(): bool;

    /**
     * Ask the question and return the answer, or $default when there is no usable answer.
     */
    public function confirm(
        string $question,
        bool $default = false,
    ): bool;
}
