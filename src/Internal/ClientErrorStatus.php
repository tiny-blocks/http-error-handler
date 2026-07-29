<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Throwable;
use TinyBlocks\Http\Code;

final readonly class ClientErrorStatus
{
    public static function from(Throwable $exception): ?Code
    {
        $code = Code::tryFromNullable($exception->getCode());

        return $code?->isClientError() === true ? $code : null;
    }

    public static function matches(int $status): bool
    {
        return $status >= Code::BAD_REQUEST->value && $status < Code::INTERNAL_SERVER_ERROR->value;
    }
}
