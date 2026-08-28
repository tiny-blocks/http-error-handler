<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Mapping;

use Throwable;
use TinyBlocks\Http\ErrorHandler\MappedError;

final readonly class FixedMappedErrorResolver implements MappedErrorResolver
{
    public function __construct(private MappedError $error)
    {
    }

    public function resolve(Throwable $exception): MappedError
    {
        return $this->error;
    }
}
