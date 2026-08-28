<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use RuntimeException;

final class SqlStateException extends RuntimeException
{
    public function __construct(string $sqlState)
    {
        parent::__construct(message: 'Base table or view not found.');
        $this->code = $sqlState;
    }
}
