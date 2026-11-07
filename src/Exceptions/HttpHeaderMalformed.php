<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a header configured on a mapped error is not a well-formed HTTP response field.
 *
 * <p>A connection-specific header (<code>Connection</code>, <code>Keep-Alive</code>,
 * <code>Proxy-Connection</code>, <code>Transfer-Encoding</code>, <code>Upgrade</code>, or <code>TE</code> with any
 * value other than <code>trailers</code>) is not one: HTTP/2 and HTTP/3 treat a response carrying it as
 * malformed.</p>
 */
final class HttpHeaderMalformed extends InvalidArgumentException
{
}
