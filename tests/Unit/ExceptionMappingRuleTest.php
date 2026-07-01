<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class ExceptionMappingRuleTest extends TestCase
{
    public function testMapsToWhenHeadersOmittedThenMappedErrorHasEmptyHeaders(): void
    {
        /** @Given a rule for RuntimeException with no headers specified */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @When mapping a RuntimeException */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the MappedError has an empty headers array */
        self::assertNotNull($result);
        self::assertSame([], $result->headers);
    }

    public function testMapsToWhenHeadersProvidedThenHeadersPropagatedToMappedError(): void
    {
        /** @Given a set of headers */
        $headers = ['Retry-After' => '60'];

        /** @And a rule that includes those headers */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 429, message: 'Too many requests.', headers: $headers);

        /** @When mapping a RuntimeException */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the MappedError carries those headers */
        self::assertNotNull($result);
        self::assertSame($headers, $result->headers);
    }

    public function testResolvesWithWhenClosureReturnsMappedErrorThenMappedErrorIsReturned(): void
    {
        /** @Given a RuntimeException with a specific message */
        $exception = new RuntimeException('Gateway unavailable.');

        /** @And a table with a rule that builds a MappedError from the exception */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->resolvesWith(
                resolver: fn(Throwable $thrownException): MappedError => new MappedError(
                    code: 'GATEWAY_UNAVAILABLE',
                    status: 502,
                    message: $thrownException->getMessage()
                )
            );

        /** @When mapping the exception */
        $result = $table->mapTo(exception: $exception);

        /** @Then the MappedError reflects the exception's message */
        self::assertNotNull($result);
        self::assertSame('Gateway unavailable.', $result->message);
    }

    public function testMapsToReturnsNewTableInstance(): void
    {
        /** @Given a table to derive a rule from */
        $originalTable = ExceptionMappingTable::create();

        /** @When calling mapsTo on a rule derived from the original table */
        $newTable = $originalTable->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @Then a new table instance is returned */
        self::assertNotSame($originalTable, $newTable);
    }

    public function testResolvesWithReturnsNewTableInstance(): void
    {
        /** @Given a table to derive a rule from */
        $originalTable = ExceptionMappingTable::create();

        /** @When calling resolvesWith on a rule derived from the original table */
        $newTable = $originalTable->when(exceptionClass: RuntimeException::class)
            ->resolvesWith(
                resolver: fn(Throwable $thrownException): MappedError => new MappedError(
                    code: 'ERR',
                    status: 500,
                    message: $thrownException->getMessage()
                )
            );

        /** @Then a new table instance is returned */
        self::assertNotSame($originalTable, $newTable);
    }
}
