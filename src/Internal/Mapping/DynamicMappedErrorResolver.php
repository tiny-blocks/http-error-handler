<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Mapping;

use Closure;
use Throwable;
use TinyBlocks\Http\ErrorHandler\MappedError;

final readonly class DynamicMappedErrorResolver implements MappedErrorResolver
{
    public function __construct(private Closure $factory)
    {
    }

    public function resolve(Throwable $exception): MappedError
    {
        return ($this->factory)($exception);
    }
}
