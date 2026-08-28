<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Mapping;

use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMatcher;

final readonly class ExactClassMatcher implements ExceptionMatcher
{
    public function __construct(private string $exceptionClass)
    {
    }

    public function matches(Throwable $exception): bool
    {
        return $exception::class === $this->exceptionClass;
    }
}
