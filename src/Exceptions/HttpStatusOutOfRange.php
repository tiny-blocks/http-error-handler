<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the HTTP status configured on a mapped error is not a known HTTP error status.
 */
final class HttpStatusOutOfRange extends InvalidArgumentException
{
}
