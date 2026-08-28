<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal\Mapping;

use Exception;
use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TinyBlocks\Http\ErrorHandler\Internal\Mapping\ExactClassMatcher;

final class ExactClassMatcherTest extends TestCase
{
    public function testMatchesWhenExceptionClassIsExactMatchThenReturnsTrue(): void
    {
        /** @Given an ExactClassMatcher configured for RuntimeException */
        $matcher = new ExactClassMatcher(exceptionClass: RuntimeException::class);

        /** @When matching a RuntimeException instance */
        $result = $matcher->matches(exception: new RuntimeException());

        /** @Then the result is true */
        self::assertTrue($result);
    }

    public function testMatchesWhenExceptionIsParentClassThenReturnsFalse(): void
    {
        /** @Given an ExactClassMatcher configured for RuntimeException */
        $matcher = new ExactClassMatcher(exceptionClass: RuntimeException::class);

        /** @When matching an Exception instance (parent of RuntimeException) */
        $result = $matcher->matches(exception: new Exception());

        /** @Then the result is false */
        self::assertFalse($result);
    }

    public function testMatchesWhenExceptionIsSubclassThenReturnsFalse(): void
    {
        /** @Given an ExactClassMatcher configured for RuntimeException */
        $matcher = new ExactClassMatcher(exceptionClass: RuntimeException::class);

        /** @When matching an OverflowException instance (subclass of RuntimeException) */
        $result = $matcher->matches(exception: new OverflowException());

        /** @Then the result is false */
        self::assertFalse($result);
    }

    public function testMatchesWhenExceptionIsUnrelatedTypeThenReturnsFalse(): void
    {
        /** @Given an ExactClassMatcher configured for RuntimeException */
        $matcher = new ExactClassMatcher(exceptionClass: RuntimeException::class);

        /** @When matching a LogicException instance (unrelated type) */
        $result = $matcher->matches(exception: new LogicException());

        /** @Then the result is false */
        self::assertFalse($result);
    }
}
