<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

use Throwable;
use TinyBlocks\Http\Code;

final readonly class ClientErrorStatus
{
    public static function from(Throwable $exception): ?Code
    {
        $declaredCode = $exception->getCode();
        $code = is_int($declaredCode) ? Code::tryFromNullable(code: $declaredCode) : null;

        return $code?->isClientError() === true ? $code : null;
    }
}
