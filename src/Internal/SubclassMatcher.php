<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMatcher;

final readonly class SubclassMatcher implements ExceptionMatcher
{
    public function __construct(private string $baseException)
    {
    }

    public function matches(Throwable $exception): bool
    {
        return $exception instanceof $this->baseException;
    }
}
