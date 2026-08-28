<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Exceptions;

use LogicException;

/**
 * Thrown when the middleware is built without a single exception mapping registered.
 */
final class MappingNotConfigured extends LogicException
{
}
