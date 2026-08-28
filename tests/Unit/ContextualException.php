<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use RuntimeException;
use TinyBlocks\Http\ErrorHandler\ReportingContext;

final class ContextualException extends RuntimeException implements ReportingContext
{
    public function __construct(private readonly array $tags, int $status = 0)
    {
        parent::__construct(message: 'The upstream service is unavailable.', code: $status);
    }

    public function reportingTags(): array
    {
        return $this->tags;
    }
}
