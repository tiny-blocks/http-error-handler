<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use LogicException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\MappedError;

final class ExceptionMappingTableTest extends TestCase
{
    public function testWhenMapsToReturnsNewTableInstance(): void
    {
        /** @Given an original empty table */
        $originalTable = ExceptionMappingTable::create();

        /** @When appending a rule via when()->mapsTo() */
        $newTable = $originalTable->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @Then the returned table is a different instance from the original */
        self::assertNotSame($originalTable, $newTable);
    }

    public function testMapToWhenTableIsEmptyThenReturnsNull(): void
    {
        /** @When mapping any exception on an empty table */
        $result = ExceptionMappingTable::create()->mapTo(exception: new RuntimeException());

        /** @Then null is returned */
        self::assertNull($result);
    }

    public function testWhenAnyMapsToReturnsNewTableInstance(): void
    {
        /** @Given an original empty table */
        $originalTable = ExceptionMappingTable::create();

        /** @When appending a rule via whenAny()->mapsTo() */
        $newTable = $originalTable->whenAny(exceptionClasses: [RuntimeException::class])
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @Then the returned table is a different instance from the original */
        self::assertNotSame($originalTable, $newTable);
    }

    public function testMapToWhenNoRuleMatchesThenReturnsNull(): void
    {
        /** @Given a table with a rule only for RuntimeException */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @When mapping a LogicException (not registered) */
        $result = $table->mapTo(exception: new LogicException());

        /** @Then null is returned */
        self::assertNull($result);
    }

    public function testWhenSubclassOfMapsToReturnsNewTableInstance(): void
    {
        /** @Given an original empty table */
        $originalTable = ExceptionMappingTable::create();

        /** @When appending a rule via whenSubclassOf()->mapsTo() */
        $newTable = $originalTable->whenSubclassOf(baseException: RuntimeException::class)
            ->mapsTo(code: 'ERR', status: 400, message: 'error');

        /** @Then the returned table is a different instance from the original */
        self::assertNotSame($originalTable, $newTable);
    }

    public function testMapToWhenMultipleRulesMatchThenFirstRegisteredWins(): void
    {
        /** @Given a table with a specific rule for OverflowException registered first */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: OverflowException::class)
            ->mapsTo(code: 'OVERFLOW', status: 400, message: 'Overflow.')
            ->whenSubclassOf(baseException: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME_FAMILY', status: 500, message: 'Runtime family.');

        /** @When mapping an OverflowException (matches both rules) */
        $result = $table->mapTo(exception: new OverflowException());

        /** @Then the first registered rule wins */
        self::assertNotNull($result);
        self::assertSame('OVERFLOW', $result->code);
    }

    public function testMapToWhenSubclassOfBaseClassThenReturnsMappedError(): void
    {
        /** @Given a table with a rule for any subclass of RuntimeException */
        $table = ExceptionMappingTable::create()
            ->whenSubclassOf(baseException: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME_FAMILY', status: 500, message: 'Runtime family error.');

        /** @When mapping an OverflowException (subclass of RuntimeException) */
        $result = $table->mapTo(exception: new OverflowException());

        /** @Then the MappedError for the base class rule is returned */
        self::assertNotNull($result);
        self::assertSame('RUNTIME_FAMILY', $result->code);
    }

    public function testMergedWithWhenOnlyOtherTableMatchesThenOtherRuleResolves(): void
    {
        /** @Given a table that only maps RuntimeException */
        $primary = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME', status: 409, message: 'Runtime translation.');

        /** @And another table that maps LogicException */
        $secondary = ExceptionMappingTable::create()
            ->when(exceptionClass: LogicException::class)
            ->mapsTo(code: 'LOGIC', status: 422, message: 'Logic translation.');

        /** @When mapping a LogicException on the merged table */
        $result = $primary->mergedWith(other: $secondary)->mapTo(exception: new LogicException());

        /** @Then the other table's rule resolves the exception */
        self::assertNotNull($result);
        self::assertSame('LOGIC', $result->code);
    }

    public function testMergedWithWhenBothTablesMatchThenThisTableTakesPrecedence(): void
    {
        /** @Given a table that maps RuntimeException to a conflict */
        $primary = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'PRIMARY', status: 409, message: 'Primary translation.');

        /** @And another table that maps the same exception to an unprocessable entity */
        $secondary = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'SECONDARY', status: 422, message: 'Secondary translation.');

        /** @When mapping a RuntimeException on the merged table */
        $result = $primary->mergedWith(other: $secondary)->mapTo(exception: new RuntimeException());

        /** @Then this table's rule wins */
        self::assertNotNull($result);
        self::assertSame('PRIMARY', $result->code);
    }

    public function testMapToWhenSameClassRegisteredTwiceThenFirstRegistrationWins(): void
    {
        /** @Given a table with two rules for the same exception class */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'FIRST', status: 400, message: 'first registration')
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'SECOND', status: 404, message: 'second registration');

        /** @When mapping a RuntimeException */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the first registration's code is returned */
        self::assertNotNull($result);
        self::assertSame('FIRST', $result->code);
    }

    public function testMapToWhenAnyExactClassMatchesListedExceptionThenReturnsMappedError(): void
    {
        /** @Given a table with a rule for two listed exception classes */
        $table = ExceptionMappingTable::create()
            ->whenAny(exceptionClasses: [RuntimeException::class, LogicException::class])
            ->mapsTo(code: 'KNOWN_ERR', status: 400, message: 'Known error.');

        /** @When mapping a LogicException (second in the list) */
        $result = $table->mapTo(exception: new LogicException());

        /** @Then the registered MappedError is returned */
        self::assertNotNull($result);
        self::assertSame('KNOWN_ERR', $result->code);
    }

    public function testMapToWhenSubclassOfBaseClassAndExactInstanceThenReturnsMappedError(): void
    {
        /** @Given a table with a whenSubclassOf rule for RuntimeException */
        $table = ExceptionMappingTable::create()
            ->whenSubclassOf(baseException: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME_FAMILY', status: 500, message: 'Runtime family error.');

        /** @When mapping a RuntimeException (the exact base class) */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the MappedError is returned */
        self::assertNotNull($result);
        self::assertSame('RUNTIME_FAMILY', $result->code);
    }

    public function testMapToWhenExactClassMatchesRegisteredExceptionThenReturnsMappedError(): void
    {
        /** @Given a table with a RuntimeException rule */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME_ERR', status: 500, message: 'Runtime error.');

        /** @When mapping a RuntimeException */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the registered MappedError is returned with the correct fields */
        self::assertNotNull($result);
        self::assertSame('RUNTIME_ERR', $result->code);
        self::assertSame(500, $result->status);

        /** @And the message is as configured */
        self::assertSame('Runtime error.', $result->message);
    }

    public function testMapToWhenAnyExactClassMatchesFirstListedExceptionThenReturnsMappedError(): void
    {
        /** @Given a table with a rule for two listed exception classes */
        $table = ExceptionMappingTable::create()
            ->whenAny(exceptionClasses: [RuntimeException::class, LogicException::class])
            ->mapsTo(code: 'KNOWN_ERR', status: 400, message: 'Known error.');

        /** @When mapping a RuntimeException (first in the list) */
        $result = $table->mapTo(exception: new RuntimeException());

        /** @Then the registered MappedError is returned */
        self::assertNotNull($result);
        self::assertSame('KNOWN_ERR', $result->code);
    }

    public function testMapToWhenExactClassMatchesAndClosureRegisteredThenClosureReceivesException(): void
    {
        /** @Given a RuntimeException with a specific message */
        $exception = new RuntimeException('Payment gateway timeout.');

        /** @And a table with a resolver that reads the exception message */
        $table = ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->resolvesWith(
                resolver: fn(Throwable $thrownException): MappedError => new MappedError(
                    code: 'GATEWAY_ERR',
                    status: 502,
                    message: $thrownException->getMessage()
                )
            );

        /** @When mapping the exception */
        $result = $table->mapTo(exception: $exception);

        /** @Then the MappedError reflects what the closure returned using the exception */
        self::assertNotNull($result);
        self::assertSame('Payment gateway timeout.', $result->message);
    }
}
