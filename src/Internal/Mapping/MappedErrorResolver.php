<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Mapping;

use Throwable;
use TinyBlocks\Http\ErrorHandler\MappedError;

/**
 * Factory that produces the MappedError payload for a matched throwable.
 */
interface MappedErrorResolver
{
    /**
     * Produces a MappedError for the given matched exception.
     *
     * @param Throwable $exception The matched exception.
     * @return MappedError The structured error response for the exception.
     */
    public function resolve(Throwable $exception): MappedError;
}
