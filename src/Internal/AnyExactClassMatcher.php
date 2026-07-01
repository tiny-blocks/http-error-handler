<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMatcher;

final readonly class AnyExactClassMatcher implements ExceptionMatcher
{
    public function __construct(private array $exceptionClasses)
    {
    }

    public function matches(Throwable $exception): bool
    {
        return in_array($exception::class, $this->exceptionClasses, true);
    }
}
