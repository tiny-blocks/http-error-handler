<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpHeaderMalformed;
use TinyBlocks\Http\ErrorHandler\Exceptions\HttpStatusOutOfRange;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class MappedErrorTest extends TestCase
{
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

    public function testConstructionWhenHeadersAreOmittedThenDefaultsToEmptyArray(): void
    {
        /** @When constructing a MappedError without providing headers */
        $error = new MappedError(code: 'ERR', status: 422, message: 'An error occurred.');

        /** @Then headers should default to an empty array */
        self::assertSame([], $error->headers);
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

    public function testConstructionWhenHeaderNameIsMalformedThenHttpHeaderMalformedIsThrown(): void
    {
        /** @Given a header whose name carries a character no HTTP field name admits */
        $headers = ["Retry\nAfter" => '60'];

        /** @Then a malformed header exception should be thrown */
        $this->expectException(HttpHeaderMalformed::class);

        /** @When constructing a MappedError with that header */
        new MappedError(code: 'ERR', status: 429, message: 'An error occurred.', headers: $headers);
    }

    public function testConstructionWhenStatusHasNoKnownCaseThenHttpStatusOutOfRangeIsThrown(): void
    {
        /** @Then an exception indicating the status is out of range should be thrown */
        $this->expectException(HttpStatusOutOfRange::class);
        $this->expectExceptionMessage('HTTP status <419> is not a known HTTP error status.');

        /** @When constructing a MappedError from a status inside the range that no HTTP status names */
        new MappedError(code: 'ERR', status: 419, message: 'msg');
    }

    public function testConstructionWhenHeaderValueIsMalformedThenHttpHeaderMalformedIsThrown(): void
    {
        /** @Given a well-formed name carrying a value that breaks the field across lines */
        $headers = ['Retry-After' => "60\r\nX-Injected: 1"];

        /** @Then a malformed header exception should be thrown */
        $this->expectException(HttpHeaderMalformed::class);

        /** @When constructing a MappedError with that header */
        new MappedError(code: 'ERR', status: 429, message: 'An error occurred.', headers: $headers);
    }

    public function testConstructionWhenStatusExceedsUpperBoundaryThenHttpStatusOutOfRangeIsThrown(): void
    {
        /** @Then an exception indicating the status is out of range should be thrown */
        $this->expectException(HttpStatusOutOfRange::class);
        $this->expectExceptionMessage('HTTP status <600> is not a known HTTP error status.');

        /** @When constructing a MappedError above the upper boundary */
        new MappedError(code: 'ERR', status: 600, message: 'msg');
    }

    public function testConstructionWhenStatusIsBelowLowerBoundaryThenHttpStatusOutOfRangeIsThrown(): void
    {
        /** @Then an exception indicating the status is out of range should be thrown */
        $this->expectException(HttpStatusOutOfRange::class);
        $this->expectExceptionMessage('HTTP status <399> is not a known HTTP error status.');

        /** @When constructing a MappedError below the lower boundary */
        new MappedError(code: 'ERR', status: 399, message: 'msg');
    }

    public function testConstructionWhenMultiValueHeaderCarriesMalformedValueThenHttpHeaderMalformedIsThrown(): void
    {
        /** @Given a multi-value header whose second value breaks the field across lines */
        $headers = ['X-Custom' => ['a', "b\r\nX-Injected: 1"]];

        /** @Then a malformed header exception should be thrown */
        $this->expectException(HttpHeaderMalformed::class);

        /** @When constructing a MappedError with that header */
        new MappedError(code: 'ERR', status: 422, message: 'An error occurred.', headers: $headers);
    }

    public function testConstructionWhenNumericHeaderNameCarriesMalformedValueThenHttpHeaderMalformedIsThrown(): void
    {
        /** @Given a numeric header name, which PHP stores as an integer key, carrying a malformed value */
        $headers = ['404' => "x\r\n"];

        /** @Then a malformed header exception should be thrown */
        $this->expectException(HttpHeaderMalformed::class);

        /** @When constructing a MappedError with that header */
        new MappedError(code: 'ERR', status: 422, message: 'An error occurred.', headers: $headers);
    }
}
