<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;

/**
 * Error response the middleware answers with, which knows what it will say before it says it.
 *
 * <p>The payload is resolved first and rendered second, so the middleware reads the status and the
 * message from the values it already holds, never back out of a rendered response, and whoever
 * rewrites the message never has to parse one.</p>
 */
interface ErrorResponse
{
    /**
     * Returns the body about to be answered with, before the message is final.
     *
     * @return ErrorPayload The resolved code, status and message.
     */
    public function payload(): ErrorPayload;

    /**
     * Returns the ErrorResponse as a PSR-7 response, carrying the message the caller settled on.
     *
     * @param string $message The message the response answers with.
     * @return ResponseInterface The rendered response.
     */
    public function toResponse(string $message): ResponseInterface;
}
