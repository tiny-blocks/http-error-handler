<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TinyBlocks\Http\ErrorHandler\Internal\FixedMappedErrorResolver;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class FixedMappedErrorResolverTest extends TestCase
{
    public function testResolveWhenAnyExceptionProvidedThenReturnsConfiguredMappedError(): void
    {
        /** @Given a fixed MappedError */
        $mappedError = new MappedError(code: 'NOT_FOUND', status: 404, message: 'Resource not found.');

        /** @And a FixedMappedErrorResolver configured with that MappedError */
        $resolver = new FixedMappedErrorResolver(error: $mappedError);

        /** @When resolving with any exception */
        $result = $resolver->resolve(exception: new RuntimeException());

        /** @Then the exact same MappedError instance is returned */
        self::assertSame($mappedError, $result);
    }

    public function testResolveWhenDifferentExceptionTypeProvidedThenAlwaysReturnsConfiguredMappedError(): void
    {
        /** @Given a fixed MappedError */
        $mappedError = new MappedError(code: 'ERR', status: 500, message: 'Error.');

        /** @And a FixedMappedErrorResolver configured with that MappedError */
        $resolver = new FixedMappedErrorResolver(error: $mappedError);

        /** @When resolving with a different exception type */
        $result = $resolver->resolve(exception: new LogicException());

        /** @Then the same MappedError instance is returned regardless of exception type */
        self::assertSame($mappedError, $result);
    }
}
