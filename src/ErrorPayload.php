<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use TinyBlocks\Http\Code;

/**
 * Body the middleware is about to answer with, before the message is final.
 *
 * <p>An application that speaks to people rather than to machines rewrites the message, and the
 * rest of the answer stays as the middleware resolved it. Handing it this, rather than the rendered
 * response, is what keeps the rewrite from being a parse and a re-encode of what was just built.</p>
 */
final readonly class ErrorPayload
{
    public function __construct(
        public string $code,
        public Code $status,
        public string $message,
        public bool $wasMapped
    ) {
    }
}
