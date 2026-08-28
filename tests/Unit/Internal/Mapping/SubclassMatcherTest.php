<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal\Mapping;

use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TinyBlocks\Http\ErrorHandler\Internal\Mapping\SubclassMatcher;

final class SubclassMatcherTest extends TestCase
{
    public function testMatchesWhenExceptionIsExactBaseClassThenReturnsTrue(): void
    {
        /** @Given a SubclassMatcher configured for RuntimeException */
        $matcher = new SubclassMatcher(baseException: RuntimeException::class);

        /** @When matching a RuntimeException instance */
        $result = $matcher->matches(exception: new RuntimeException());

        /** @Then the result is true */
        self::assertTrue($result);
    }

    public function testMatchesWhenExceptionIsUnrelatedTypeThenReturnsFalse(): void
    {
        /** @Given a SubclassMatcher configured for RuntimeException */
        $matcher = new SubclassMatcher(baseException: RuntimeException::class);

        /** @When matching a LogicException (not a subclass of RuntimeException) */
        $result = $matcher->matches(exception: new LogicException());

        /** @Then the result is false */
        self::assertFalse($result);
    }

    public function testMatchesWhenExceptionIsSubclassOfBaseClassThenReturnsTrue(): void
    {
        /** @Given a SubclassMatcher configured for RuntimeException */
        $matcher = new SubclassMatcher(baseException: RuntimeException::class);

        /** @When matching an OverflowException (subclass of RuntimeException) */
        $result = $matcher->matches(exception: new OverflowException());

        /** @Then the result is true */
        self::assertTrue($result);
    }
}
