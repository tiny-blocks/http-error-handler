<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the HTTP status configured on a mapped error falls outside the 400-599 range.
 */
final class HttpStatusOutOfRange extends InvalidArgumentException
{
}
