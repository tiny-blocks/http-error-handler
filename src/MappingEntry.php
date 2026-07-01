<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Throwable;

/**
 * Entry in an {@see ExceptionMappingTable} that resolves a matched throwable to a {@see MappedError}.
 */
interface MappingEntry
{
    /**
     * Returns the mapped error for the exception, or null when this entry does not match it.
     *
     * @param Throwable $exception The exception to resolve.
     * @return MappedError|null The mapped error, or <code>null</code> when this entry does not match.
     */
    public function resolve(Throwable $exception): ?MappedError;
}
