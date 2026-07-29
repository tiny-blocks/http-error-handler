<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use LogicException;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;

final readonly class UnprocessableExceptions implements ExceptionMapping
{
    public function mappings(): ExceptionMappingTable
    {
        return ExceptionMappingTable::create()
            ->when(exceptionClass: LogicException::class)
            ->mapsTo(
                code: 'INVALID_REQUEST',
                status: Code::UNPROCESSABLE_ENTITY->value,
                message: 'The request payload is not valid.'
            );
    }
}
