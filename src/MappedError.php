<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpStatusOutOfRange;

/**
 * Describes an exception that has been mapped to a structured HTTP error response, with a
 * machine-readable code, an HTTP status, and a human-readable message.
 */
final readonly class MappedError
{
    /**
     * @param array<string, string|string[]> $headers HTTP response headers to include when this error is returned.
     */
    public function __construct(
        public string $code,
        public int $status,
        public string $message,
        public array $headers = []
    ) {
        if (!Code::isErrorCode(code: $status)) {
            $template = 'HTTP status <%d> must be between 400 and 599.';

            throw new HttpStatusOutOfRange(message: sprintf($template, $status));
        }
    }
}
