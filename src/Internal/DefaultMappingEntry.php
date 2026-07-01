<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMatcher;
use TinyBlocks\Http\ErrorHandler\MappedError;
use TinyBlocks\Http\ErrorHandler\MappingEntry;

final readonly class DefaultMappingEntry implements MappingEntry
{
    public function __construct(private ExceptionMatcher $matcher, private MappedErrorResolver $resolver)
    {
    }

    public function resolve(Throwable $exception): ?MappedError
    {
        if (!$this->matcher->matches(exception: $exception)) {
            return null;
        }

        return $this->resolver->resolve(exception: $exception);
    }
}
