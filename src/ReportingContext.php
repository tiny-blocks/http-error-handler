<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Supplement an exception carries for whoever reports it.
 *
 * <p>The middleware knows the request and the resolved status, and nothing beyond that. What made
 * this failure specific, the service that refused, the tenant it belonged to, is known only where
 * the exception was raised, so the exception is what carries it.</p>
 *
 * <p>An exception implements this when it has something to add. One that does not is reported on
 * the request alone, which is the behavior every exception had before this existed. An exception
 * that instead disagrees about how much attention it deserves implements {@see PrioritizedError},
 * and one that does both implements both.</p>
 */
interface ReportingContext
{
    /**
     * Values that identify this failure beyond the request that provoked it.
     *
     * <p>Every entry becomes an index at the provider, so a key stays stable across releases and a
     * value stays drawn from a set the reader can reason about.</p>
     *
     * @return array<string, string> Entries keyed by name, empty when the exception adds nothing.
     */
    public function reportingTags(): array;
}
