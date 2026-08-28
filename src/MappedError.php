<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpHeaderMalformed;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpStatusOutOfRange;
use TinyBlocks\Http\ErrorHandler\Internal\Response\ResponseHeaders;

/**
 * Describes an exception that has been mapped to a structured HTTP error response, with a
 * machine-readable code, an HTTP status, and a human-readable message.
 */
final readonly class MappedError
{
    public function __construct(
        public string $code,
        public int $status,
        public string $message,
        public array $headers = []
    ) {
        if (Code::tryFromNullable(code: $status)?->isError() !== true) {
            $template = 'HTTP status <%d> is not a known HTTP error status.';

            throw new HttpStatusOutOfRange(message: sprintf($template, $status));
        }

        $malformed = ResponseHeaders::malformedIn(headers: $headers);

        if (!is_null($malformed)) {
            $template = 'HTTP header <%s> is malformed.';

            throw new HttpHeaderMalformed(message: sprintf($template, $malformed));
        }
    }
}
