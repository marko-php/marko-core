<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Throwable;

/**
 * An exception that carries an HTTP meaning.
 *
 * Any package can throw one without depending on the routing or errors
 * packages: the routing pipeline turns it into a response with this status,
 * these headers and this data, and the error handlers use its status as a
 * last resort. Only data returned by getResponseData() is ever shown to the
 * client — the exception message is never sent unless an implementation
 * explicitly puts it there.
 */
interface HttpExceptionInterface extends Throwable
{
    /**
     * The HTTP status code to respond with (400-599).
     */
    public function getStatusCode(): int;

    /**
     * Headers to add to the response, e.g. `Allow` or `Retry-After`.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Data safe to show the client, rendered as the JSON body or passed to the
     * HTML page. A `message` key, when present, is the human-readable message.
     *
     * @return array<string, mixed>
     */
    public function getResponseData(): array;
}
