<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TinyBlocks\Http\ErrorHandler\Internal\DynamicMappedErrorResolver;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class DynamicMappedErrorResolverTest extends TestCase
{
    public function testResolveWhenInvokedThenClosureReceivesTheThrownException(): void
    {
        /** @Given a RuntimeException to pass to the resolver */
        $exception = new RuntimeException('Gateway timed out.');

        /** @And a variable to capture the exception received by the closure */
        $received = null;

        /** @And a resolver whose closure captures what it receives */
        $resolver = new DynamicMappedErrorResolver(
            factory: function (Throwable $thrownException) use (&$received): MappedError {
                $received = $thrownException;
                return new MappedError(code: 'ERR', status: 500, message: 'Error.');
            }
        );

        /** @When resolving with the exception */
        $resolver->resolve(exception: $exception);

        /** @Then the closure received the original exception instance */
        self::assertSame($exception, $received);
    }

    public function testResolveWhenClosureReadsExceptionFieldsThenReturnsMappedErrorWithThoseFields(): void
    {
        /** @Given a RuntimeException whose message will drive the MappedError */
        $exception = new RuntimeException('Gateway unavailable.');

        /** @And a resolver that builds a MappedError from the exception message */
        $resolver = new DynamicMappedErrorResolver(
            factory: fn(Throwable $thrownException): MappedError => new MappedError(
                code: 'GATEWAY_UNAVAILABLE',
                status: 502,
                message: $thrownException->getMessage()
            )
        );

        /** @When resolving the exception */
        $result = $resolver->resolve(exception: $exception);

        /** @Then the resolved MappedError carries the exception message */
        self::assertSame('Gateway unavailable.', $result->message);
    }
}
