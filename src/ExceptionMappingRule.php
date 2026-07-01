<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Closure;
use Throwable;
use TinyBlocks\Http\ErrorHandler\Internal\DefaultMappingEntry;
use TinyBlocks\Http\ErrorHandler\Internal\DynamicMappedErrorResolver;
use TinyBlocks\Http\ErrorHandler\Internal\FixedMappedErrorResolver;

/**
 * Intermediate builder closing a rule registered on an {@see ExceptionMappingTable}. A rule
 * resolves either to a fixed {@see MappedError} (<code>mapsTo</code>) or to one produced by a
 * closure that receives the matched throwable (<code>resolvesWith</code>).
 */
final readonly class ExceptionMappingRule
{
    public function __construct(private ExceptionMappingTable $table, private ExceptionMatcher $matcher)
    {
    }

    /**
     * Closes the rule with a fixed MappedError produced from the given fields.
     *
     * @param string $code Machine-readable error code.
     * @param int $status HTTP response status code (400-599).
     * @param string $message Human-readable error description.
     * @param array<string, string|string[]> $headers Optional HTTP response headers.
     * @return ExceptionMappingTable The table with the new rule appended.
     */
    public function mapsTo(string $code, int $status, string $message, array $headers = []): ExceptionMappingTable
    {
        return $this->table->withEntry(
            entry: new DefaultMappingEntry(
                matcher: $this->matcher,
                resolver: new FixedMappedErrorResolver(
                    error: new MappedError(
                        code: $code,
                        status: $status,
                        message: $message,
                        headers: $headers
                    )
                )
            )
        );
    }

    /**
     * Closes the rule with a closure that produces a MappedError from the matched exception.
     *
     * <p>The closure parameter may be typed more specifically than {@see Throwable} when the
     * matcher guarantees the runtime type before the closure is invoked.</p>
     *
     * @param Closure(Throwable): MappedError $resolver The closure to invoke with the matched exception.
     * @return ExceptionMappingTable The table with the new rule appended.
     */
    public function resolvesWith(Closure $resolver): ExceptionMappingTable
    {
        return $this->table->withEntry(
            entry: new DefaultMappingEntry(
                matcher: $this->matcher,
                resolver: new DynamicMappedErrorResolver(factory: $resolver)
            )
        );
    }
}
