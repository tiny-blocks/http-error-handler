<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;

final readonly class RequestCorrelationId
{
    public static function from(ServerRequestInterface $request): ?CorrelationId
    {
        $correlationId = $request->getAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME);

        return $correlationId instanceof CorrelationId ? $correlationId : null;
    }
}
