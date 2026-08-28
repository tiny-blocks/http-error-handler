<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\CorrelationId\CorrelationId;

/**
 * Resolved context of an error the middleware has handled, carrying everything an
 * {@see ErrorReporter} needs to forward it to a provider without reaching back into the request.
 *
 * <p>The status is the one the middleware already resolved for the response, so a reporter never
 * reclassifies the error, and the correlation identifier is the one the request carries, so a
 * reporter never learns where it is stored.</p>
 *
 * <p>The path is high cardinality and must never become a metric label. Its query string is dropped
 * on purpose, because it routinely carries credentials and personal data. The route is the one
 * request dimension safe as a label, low cardinality by contract, and the remaining safe dimensions
 * are the status, the method, the mapped code and the exception class.</p>
 */
final readonly class ReportedError
{
    public function __construct(
        public string $path,
        public string $method,
        public Code $status,
        public ReportingPriority $priority,
        public Throwable $exception,
        public array $tags = [],
        public ?string $route = null,
        public ?MappedError $mapped = null,
        public ?CorrelationId $correlationId = null
    ) {
    }

    /**
     * Tells whether a registered rule matched the exception.
     *
     * <p>An unmapped exception is the signal that the application met a condition nobody described,
     * which is the case most worth reporting, and it reaches a reporter whenever the middleware
     * answers it with the fallback response. A mapped one is a declared outcome, even when its
     * status is in the 5xx range, so providers commonly treat the two differently. This answers a
     * different question from <code>status->isClientError()</code>: an unmapped exception carrying
     * a 4xx code reaches the client as a 4xx, and is a client error that no rule described.</p>
     *
     * @return bool <code>true</code> when a mapping rule matched, <code>false</code> otherwise.
     */
    public function wasMapped(): bool
    {
        return !is_null($this->mapped);
    }
}
