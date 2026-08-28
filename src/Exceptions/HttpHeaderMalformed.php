<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a header configured on a mapped error is not a well-formed HTTP field.
 */
final class HttpHeaderMalformed extends InvalidArgumentException
{
}
