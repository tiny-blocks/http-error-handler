<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Internal;

use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TinyBlocks\Http\ErrorHandler\Internal\AnyExactClassMatcher;

final class AnyExactClassMatcherTest extends TestCase
{
    public function testMatchesWhenExceptionClassIsFirstInListThenReturnsTrue(): void
    {
        /** @Given an AnyExactClassMatcher configured for RuntimeException and LogicException */
        $matcher = new AnyExactClassMatcher(exceptionClasses: [RuntimeException::class, LogicException::class]);

        /** @When matching a RuntimeException (first entry in the list) */
        $result = $matcher->matches(exception: new RuntimeException());

        /** @Then the result is true */
        self::assertTrue($result);
    }

    public function testMatchesWhenExceptionClassIsLastInListThenReturnsTrue(): void
    {
        /** @Given an AnyExactClassMatcher configured for RuntimeException and LogicException */
        $matcher = new AnyExactClassMatcher(exceptionClasses: [RuntimeException::class, LogicException::class]);

        /** @When matching a LogicException (last entry in the list) */
        $result = $matcher->matches(exception: new LogicException());

        /** @Then the result is true */
        self::assertTrue($result);
    }

    public function testMatchesWhenExceptionIsSubclassOfListedClassThenReturnsFalse(): void
    {
        /** @Given an AnyExactClassMatcher configured for RuntimeException only */
        $matcher = new AnyExactClassMatcher(exceptionClasses: [RuntimeException::class]);

        /** @When matching an OverflowException (subclass of RuntimeException) */
        $result = $matcher->matches(exception: new OverflowException());

        /** @Then the result is false because the matcher is exact-class only */
        self::assertFalse($result);
    }

    public function testMatchesWhenExceptionIsUnrelatedTypeThenReturnsFalse(): void
    {
        /** @Given an AnyExactClassMatcher configured for RuntimeException only */
        $matcher = new AnyExactClassMatcher(exceptionClasses: [RuntimeException::class]);

        /** @When matching a LogicException (not in the list) */
        $result = $matcher->matches(exception: new LogicException());

        /** @Then the result is false */
        self::assertFalse($result);
    }
}
