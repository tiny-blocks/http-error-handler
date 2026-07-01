<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpStatusOutOfRange;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class MappedErrorTest extends TestCase
{
    public function testConstructionWhenStatusIsAtLowerBoundaryThenNoExceptionIsThrown(): void
    {
        /** @Given the minimum valid HTTP error status */
        $status = 400;

        /** @When constructing a MappedError at the lower boundary */
        $error = new MappedError(code: 'ERR', status: $status, message: 'msg');

        /** @Then it should be created successfully with the expected status */
        self::assertSame(400, $error->status);
    }

    public function testConstructionWhenStatusIsAtUpperBoundaryThenNoExceptionIsThrown(): void
    {
        /** @Given the maximum valid HTTP error status */
        $status = 511;

        /** @When constructing a MappedError at the upper boundary */
        $error = new MappedError(code: 'ERR', status: $status, message: 'msg');

        /** @Then it should be created successfully with the expected status */
        self::assertSame(511, $error->status);
    }

    public function testConstructionWhenStatusIsBelowLowerBoundaryThenHttpStatusOutOfRangeIsThrown(): void
    {
        /** @Then an exception indicating the status is out of range should be thrown */
        $this->expectException(HttpStatusOutOfRange::class);
        $this->expectExceptionMessage('HTTP status <399> must be between 400 and 599.');

        /** @When constructing a MappedError below the lower boundary */
        new MappedError(code: 'ERR', status: 399, message: 'msg');
    }

    public function testConstructionWhenStatusExceedsUpperBoundaryThenHttpStatusOutOfRangeIsThrown(): void
    {
        /** @Then an exception indicating the status is out of range should be thrown */
        $this->expectException(HttpStatusOutOfRange::class);
        $this->expectExceptionMessage('HTTP status <600> must be between 400 and 599.');

        /** @When constructing a MappedError above the upper boundary */
        new MappedError(code: 'ERR', status: 600, message: 'msg');
    }

    public function testConstructionWhenHeadersAreOmittedThenDefaultsToEmptyArray(): void
    {
        /** @When constructing a MappedError without providing headers */
        $error = new MappedError(code: 'ERR', status: 422, message: 'An error occurred.');

        /** @Then headers should default to an empty array */
        self::assertSame([], $error->headers);
    }

    public function testConstructionWhenHeadersAreProvidedThenAreExposed(): void
    {
        /** @Given a set of headers to include in the error response */
        $headers = ['Retry-After' => '60'];

        /** @When constructing a MappedError with those headers */
        $error = new MappedError(
            code: 'LOGIN_THROTTLED',
            status: 429,
            message: 'Too many failed login attempts.',
            headers: $headers
        );

        /** @Then the headers should be preserved as provided */
        self::assertSame($headers, $error->headers);
    }

    public function testConstructionWhenHeaderValueIsArrayThenMultiValueIsPreserved(): void
    {
        /** @Given a multi-value header */
        $headers = ['X-Custom' => ['a', 'b']];

        /** @When constructing a MappedError with multi-value headers */
        $error = new MappedError(code: 'ERR', status: 422, message: 'An error occurred.', headers: $headers);

        /** @Then the multi-value header should be preserved */
        self::assertSame($headers, $error->headers);
    }
}
