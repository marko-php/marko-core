<?php

declare(strict_types=1);

namespace Marko\Core\Contracts;

use Marko\Core\Exceptions\MarkoException;

/**
 * A file uploaded with a request.
 *
 * Lives in core so packages that only inspect uploads (validation, media) can depend on the
 * contract without depending on an HTTP implementation. `marko/routing` provides the
 * implementation (`Marko\Routing\Http\UploadedFile`).
 *
 * Everything the client sent (filename, media type) is untrusted: use mimeType() and
 * guessExtension(), which inspect the file contents, for any decision that matters.
 */
interface UploadedFileInterface
{
    /**
     * The filename the client sent. Untrusted: never use it as a storage path or to decide the type.
     */
    public function clientFilename(): string;

    /**
     * The media type the client sent. Untrusted: use mimeType() instead.
     */
    public function clientMediaType(): string;

    /**
     * The size in bytes.
     */
    public function size(): int;

    /**
     * The PHP upload error code (one of the UPLOAD_ERR_* constants).
     */
    public function error(): int;

    /**
     * Whether the upload succeeded and the file has not been moved yet.
     */
    public function isValid(): bool;

    public function isMoved(): bool;

    /**
     * Move the uploaded file to its permanent location. Can be called once.
     *
     * @throws MarkoException when the upload failed, was already moved, or cannot be moved
     */
    public function moveTo(
        string $targetPath,
    ): void;

    /**
     * Open the uploaded file for reading. The caller closes the returned stream.
     *
     * @return resource
     * @throws MarkoException when the upload failed, was moved, or cannot be read
     */
    public function stream(): mixed;

    /**
     * @throws MarkoException when the upload failed, was moved, or cannot be read
     */
    public function contents(): string;

    /**
     * The real MIME type, detected from the file contents.
     *
     * @throws MarkoException when the upload failed, was moved, or cannot be read
     */
    public function mimeType(): string;

    /**
     * A file extension (without the dot) for the real MIME type, or null when it is unknown.
     *
     * @throws MarkoException when the upload failed, was moved, or cannot be read
     */
    public function guessExtension(): ?string;
}
