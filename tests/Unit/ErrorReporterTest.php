<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

final class ErrorReporterTest extends TestCase
{
    public function testNotifiesEveryRegisteredReporter(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware with two reporters registered, as distinct providers would be */
        $first = new RecordingReporter();
        $second = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $first)
            ->withReporter(reporter: $second)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then both receive the very same context */
        self::assertNotNull($first->reported);
        self::assertNotNull($second->reported);
        self::assertSame($first->reported, $second->reported);
    }

    public function testReportsNoRouteWhenNothingNamesIt(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/v1/orders/1');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware with no route middleware in the stack */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the route is absent instead of guessed from the path */
        self::assertNotNull($reporter->reported);
        self::assertNull($reporter->reported->route);
    }

    public function testReportsNothingWhenTheHandlerSucceeds(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler();

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then no report is produced, because nothing failed */
        self::assertNull($reporter->reported);
    }

    public function testReportsRouteWhenTheStackBelowNamesIt(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/v1/orders/1');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a reporter that records what it receives */
        $reporter = new RecordingReporter();

        /** @And a middleware whose stack names the matched route below the routing */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request through that stack */
        $middleware->process($request, new RoutingHandler(
            handler: $handler,
            middleware: RouteMiddleware::resolvedBy(
                resolver: static fn(ServerRequestInterface $request): string => 'POST /v1/orders/{id}'
            )
        ));

        /** @Then the report carries the pattern and never the path, which holds the identifier */
        self::assertNotNull($reporter->reported);
        self::assertSame('POST /v1/orders/{id}', $reporter->reported->route);
        self::assertSame('/v1/orders/1', $reporter->reported->path);
    }

    public function testReportsNothingWhenNoReporterIsRegistered(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware built without any reporter, as every existing consumer does */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response is produced as before */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testFailingReporterDoesNotStopTheRemainingOnes(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware where a failing reporter is registered before a working one */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: new UnreachableReporter())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the reporter registered after the failing one still receives the report */
        self::assertNotNull($reporter->reported);
    }

    public function testLoggingSurvivesWhenTheProviderIsUnreachable(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a logger that expects the error entry */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        /** @And a middleware whose only reporter always throws */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: new UnreachableReporter())
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: false,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
    }

    public function testReportsNoRouteWhenTheResolverMatchesNothing(): void
    {
        /** @Given a request for a path no route describes */
        $request = new ServerRequest('GET', '/v1/unknown');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a reporter that records what it receives */
        $reporter = new RecordingReporter();

        /** @And a middleware whose resolver finds no match */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request through that stack */
        $middleware->process($request, new RoutingHandler(
            handler: $handler,
            middleware: RouteMiddleware::resolvedBy(
                resolver: static fn(ServerRequestInterface $request): ?string => null
            )
        ));

        /** @Then the route stays absent */
        self::assertNotNull($reporter->reported);
        self::assertNull($reporter->reported->route);
    }

    public function testReportsUnmappedExceptionWithResolvedContext(): void
    {
        /** @Given a request carrying a path and a method */
        $request = new ServerRequest('POST', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $failure = new Exception('Boom');
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($failure);

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the reporter receives the context the middleware resolved */
        self::assertNotNull($reporter->reported);
        self::assertSame('/v1/orders', $reporter->reported->path);
        self::assertSame('POST', $reporter->reported->method);
        self::assertSame(Code::INTERNAL_SERVER_ERROR, $reporter->reported->status);
        self::assertSame($failure, $reporter->reported->exception);

        /** @And the error is flagged as unmapped and as a server error */
        self::assertFalse($reporter->reported->wasMapped());
        self::assertFalse($reporter->reported->status->isClientError());
        self::assertNull($reporter->reported->mapped);
    }

    public function testReportsCorrelationIdWhenTheRequestCarriesOne(): void
    {
        /** @Given a request carrying a correlation identifier */
        $correlationId = new readonly class implements CorrelationId {
            public function toString(): string
            {
                return '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
            }
        };
        $request = new ServerRequest('GET', '/v1/orders')
            ->withAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME, $correlationId);

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the reporter receives the identifier the request carries */
        self::assertNotNull($reporter->reported);
        self::assertSame($correlationId, $reporter->reported->correlationId);
    }

    public function testReportsMappedExceptionCarryingTheMatchedRule(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/v1/orders/1');

        /** @And a handler that throws an exception a rule maps to 422 */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new LogicException('Invalid payload'));

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnprocessableExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the reporter learns the rule matched, and which one */
        self::assertNotNull($reporter->reported);
        self::assertTrue($reporter->reported->wasMapped());
        self::assertNotNull($reporter->reported->mapped);
        self::assertSame('INVALID_REQUEST', $reporter->reported->mapped->code);

        /** @And the status is the mapped one, classified as a client error */
        self::assertSame(Code::UNPROCESSABLE_ENTITY, $reporter->reported->status);
        self::assertTrue($reporter->reported->status->isClientError());
    }

    public function testResponseSurvivesWhenTheProviderIsUnreachable(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware whose only reporter always throws */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: new UnreachableReporter())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response is produced exactly as if no reporter had been registered */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testReportsNoCorrelationIdWhenTheRequestCarriesNone(): void
    {
        /** @Given a request with no correlation identifier */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the correlation identifier is absent instead of invented */
        self::assertNotNull($reporter->reported);
        self::assertNull($reporter->reported->correlationId);
    }

    public function testReportsNothingWhenUnmappedExceptionsAreRethrown(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Boom'));

        /** @And a middleware configured to rethrow what it cannot map */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->withFallbackOnUnmapped(fallbackOnUnmapped: false)
            ->build();

        /** @Then the exception reaches the caller */
        $this->expectException(RuntimeException::class);

        /** @When the middleware processes the request */
        try {
            $middleware->process($request, $handler);
        } finally {
            /** @And nothing was reported, because no response was produced */
            self::assertNull($reporter->reported);
        }
    }

    public function testNotifiesReporterThatNamesTheParameterDifferently(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a reporter whose parameter carries a name of its own, as an implementation may choose */
        $reporter = new RenamedParameterReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then it is notified, because the contract is the type and never the parameter name */
        self::assertNotNull($reporter->reported);
    }

    public function testReportsAfterLoggingSoTheLogEntryIsNeverLostToAProvider(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a logger that records when it was called relative to the reporter */
        $sequence = new CallSequence();
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(static function () use ($sequence): void {
            $sequence->record(name: 'log');
        });

        /** @And a reporter that appends to the same sequence */
        $reporter = new SequencedReporter(sequence: $sequence);

        /** @And a middleware wiring both */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: false,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the log entry is emitted before the provider is contacted */
        self::assertSame(['log', 'report'], $sequence->recorded);
    }

    public function testReportsNoCorrelationIdWhenTheAttributeHoldsForeignValue(): void
    {
        /** @Given a request whose correlation attribute holds something that is not a CorrelationId */
        $request = new ServerRequest('GET', '/v1/orders')
            ->withAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME, 'not-a-correlation-id');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware with a reporter registered */
        $reporter = new RecordingReporter();
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the foreign value is refused instead of forwarded */
        self::assertNotNull($reporter->reported);
        self::assertNull($reporter->reported->correlationId);
    }

    public function testResponseSurvivesWhenAReporterGivenDirectlyToBuildThrows(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware built without the fluent builder, handed a reporter that always throws */
        $middleware = ErrorMiddleware::build(
            logger: null,
            message: static fn(ErrorPayload $payload): string => $payload->message,
            priority: ReportingPriority::from(...),
            mappings: new UnmappedExceptions()->mappings(),
            settings: ErrorHandlingSettings::default(),
            fallbackOnUnmapped: true,
            reporter: new UnreachableReporter()
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response is produced, because isolation does not depend on how the middleware was built */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }
}
