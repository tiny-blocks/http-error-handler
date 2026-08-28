<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use RuntimeException;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;

final readonly class UnavailableExceptions implements ExceptionMapping
{
    public function mappings(): ExceptionMappingTable
    {
        return ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(
                code: 'UPSTREAM_UNAVAILABLE',
                status: Code::SERVICE_UNAVAILABLE->value,
                message: 'The upstream service is unavailable.'
            );
    }
}
