<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Values the request accumulates for whoever reports an error raised while handling it.
 *
 * <p>An {@see ErrorMiddleware} sits above the application, so the request it holds when an exception
 * surfaces is the one from before anything downstream learned anything. What a later layer resolves,
 * the tenant behind a handle, the account a token belongs to, never reaches it, because a PSR-7
 * request is immutable and every addition below produces a copy the middleware never sees.</p>
 *
 * <p>This is the carrier that crosses that gap. The middleware leaves one on the request, any layer
 * that learns something writes into it, and the reporter reads what accumulated. It is mutable by
 * necessity and by design.</p>
 *
 * <p>Use it for what describes the request as a whole. What describes one failure belongs on the
 * exception, through {@see ReportingContext}, and wins over an entry of the same name here.</p>
 */
final class ReportingTags
{
    public const string ATTRIBUTE_NAME = 'tiny-blocks.error-handler.tags';

    /** @var array<string, string> */
    private array $entries = [];

    /**
     * Reads the carrier an error middleware left on the request.
     *
     * @param ServerRequestInterface $request The request being handled.
     * @return ReportingTags|null The carrier, or null when no error middleware sits above the caller.
     */
    public static function from(ServerRequestInterface $request): ?ReportingTags
    {
        $tags = $request->getAttribute(ReportingTags::ATTRIBUTE_NAME);

        return $tags instanceof ReportingTags ? $tags : null;
    }

    /**
     * Creates an empty carrier, for a request whose handling has not started.
     *
     * @return ReportingTags The carrier to leave on the request.
     */
    public static function pending(): ReportingTags
    {
        return new ReportingTags();
    }

    /**
     * Records a value under a name, replacing whatever the name held.
     *
     * <p>Every entry becomes an index at the provider, so a key stays stable across releases and a
     * value stays drawn from a set the reader can reason about.</p>
     *
     * @param string $key The name to record under.
     * @param string $value The value to record.
     */
    public function add(string $key, string $value): void
    {
        $this->entries[$key] = $value;
    }

    /**
     * Everything recorded so far.
     *
     * @return array<string, string> Entries keyed by name, empty when nothing was recorded.
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
