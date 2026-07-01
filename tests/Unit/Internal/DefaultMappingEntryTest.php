<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TinyBlocks\Http\ErrorHandler\Internal\DefaultMappingEntry;
use TinyBlocks\Http\ErrorHandler\Internal\ExactClassMatcher;
use TinyBlocks\Http\ErrorHandler\Internal\FixedMappedErrorResolver;
use TinyBlocks\Http\ErrorHandler\Internal\MappedErrorResolver;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class DefaultMappingEntryTest extends TestCase
{
    public function testResolveWhenMatcherDoesNotMatchThenReturnsNull(): void
    {
        /** @Given a DefaultMappingEntry that matches only RuntimeException */
        $entry = new DefaultMappingEntry(
            matcher: new ExactClassMatcher(exceptionClass: RuntimeException::class),
            resolver: new FixedMappedErrorResolver(error: new MappedError(code: 'ERR', status: 400, message: 'error'))
        );

        /** @When resolving a LogicException (not matched) */
        $result = $entry->resolve(exception: new LogicException());

        /** @Then null is returned */
        self::assertNull($result);
    }

    public function testResolveWhenMatcherMatchesThenReturnsMappedError(): void
    {
        /** @Given a fixed MappedError for RuntimeException */
        $mappedError = new MappedError(code: 'ERR', status: 500, message: 'error');

        /** @And a DefaultMappingEntry that matches RuntimeException with that MappedError */
        $entry = new DefaultMappingEntry(
            matcher: new ExactClassMatcher(exceptionClass: RuntimeException::class),
            resolver: new FixedMappedErrorResolver(error: $mappedError)
        );

        /** @When resolving a RuntimeException (matched) */
        $result = $entry->resolve(exception: new RuntimeException());

        /** @Then the MappedError is returned */
        self::assertSame($mappedError, $result);
    }

    public function testResolveWhenMatcherDoesNotMatchThenResolverIsNotInvoked(): void
    {
        /** @Given a resolver that records whether it was invoked */
        $resolver = new class implements MappedErrorResolver {
            public bool $wasInvoked = false;

            public function resolve(Throwable $exception): MappedError
            {
                $this->wasInvoked = true;
                return new MappedError(code: 'UNUSED', status: 400, message: 'unused');
            }
        };

        /** @And a DefaultMappingEntry that only matches RuntimeException */
        $entry = new DefaultMappingEntry(
            matcher: new ExactClassMatcher(exceptionClass: RuntimeException::class),
            resolver: $resolver
        );

        /** @When resolving a LogicException (not matched) */
        $entry->resolve(exception: new LogicException());

        /** @Then the resolver was not invoked */
        self::assertFalse($resolver->wasInvoked);
    }
}
