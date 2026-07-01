<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Throwable;
use TinyBlocks\Http\ErrorHandler\Internal\AnyExactClassMatcher;
use TinyBlocks\Http\ErrorHandler\Internal\ExactClassMatcher;
use TinyBlocks\Http\ErrorHandler\Internal\SubclassMatcher;

/**
 * Fluent table of exception-to-MappedError rules, evaluated in registration order. The first
 * matching rule wins, and unmatched exceptions return <code>null</code> to let the middleware
 * decide between fallback and rethrow.
 */
final readonly class ExceptionMappingTable
{
    private function __construct(private array $entries)
    {
    }

    /**
     * Creates an empty ExceptionMappingTable.
     *
     * @return ExceptionMappingTable The empty table.
     */
    public static function create(): ExceptionMappingTable
    {
        return new ExceptionMappingTable(entries: []);
    }

    /**
     * Registers a rule for an exact exception class match.
     *
     * @param class-string<Throwable> $exceptionClass The exception class to match exactly.
     * @return ExceptionMappingRule The intermediate rule builder.
     */
    public function when(string $exceptionClass): ExceptionMappingRule
    {
        return new ExceptionMappingRule(
            table: $this,
            matcher: new ExactClassMatcher(exceptionClass: $exceptionClass)
        );
    }

    /**
     * Returns the mapped error of the first rule that matches the exception, or null when none matches.
     *
     * @param Throwable $exception The exception to translate.
     * @return MappedError|null The mapped error, or <code>null</code> when no registered rule matches.
     */
    public function mapTo(Throwable $exception): ?MappedError
    {
        foreach ($this->entries as $entry) {
            $mappedError = $entry->resolve(exception: $exception);

            if (!is_null($mappedError)) {
                return $mappedError;
            }
        }

        return null;
    }

    /**
     * Registers a rule matching any of the given exception classes exactly.
     *
     * @param non-empty-list<class-string<Throwable>> $exceptionClasses The exception classes to match against.
     * @return ExceptionMappingRule The intermediate rule builder.
     */
    public function whenAny(array $exceptionClasses): ExceptionMappingRule
    {
        return new ExceptionMappingRule(
            table: $this,
            matcher: new AnyExactClassMatcher(exceptionClasses: $exceptionClasses)
        );
    }

    /**
     * Appends an entry and returns the resulting table.
     *
     * <p>Not part of the consumer-facing API. Used only by {@see ExceptionMappingRule} to append entries.</p>
     *
     * @param MappingEntry $entry The mapping entry to append.
     * @return ExceptionMappingTable The resulting table with the entry added.
     */
    public function withEntry(MappingEntry $entry): ExceptionMappingTable
    {
        return new ExceptionMappingTable(entries: [...$this->entries, $entry]);
    }

    /**
     * Returns a new table with this table's rules followed by the other table's rules.
     *
     * <p>The combined table keeps first-match-wins across both, so the rules of this table take
     * precedence over the rules of <code>$other</code> for an exception both would match.</p>
     *
     * @param ExceptionMappingTable $other The table whose rules are appended after this table's.
     * @return ExceptionMappingTable The combined table.
     */
    public function mergedWith(ExceptionMappingTable $other): ExceptionMappingTable
    {
        return new ExceptionMappingTable(entries: [...$this->entries, ...$other->entries]);
    }

    /**
     * Registers a rule matching any subclass of the given base exception class.
     *
     * @param class-string<Throwable> $baseException The base exception class to match against.
     * @return ExceptionMappingRule The intermediate rule builder.
     */
    public function whenSubclassOf(string $baseException): ExceptionMappingRule
    {
        return new ExceptionMappingRule(
            table: $this,
            matcher: new SubclassMatcher(baseException: $baseException)
        );
    }
}
