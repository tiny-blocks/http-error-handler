<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\Exceptions\MappingNotConfigured;

final class ErrorMiddlewareTest extends TestCase
{
    public function testDefaultSettingsAreSecureAndSilent(): void
    {
        /** @Given the default error handling settings */
        $settings = ErrorHandlingSettings::default();

        /** @Then logging should be disabled by default */
        self::assertFalse($settings->logErrors);

        /** @And logging details should be disabled by default */
        self::assertFalse($settings->logErrorDetails);

        /** @And display error details should be disabled by default */
        self::assertFalse($settings->displayErrorDetails);
    }

    public function testProcessWhenHandlerSucceedsThenResponsePassesThrough(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/users');

        /** @And a middleware with a mapping that declares no rules */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @And a handler that returns a successful response */
        $expected = new Response(Code::OK->value, [], '{"status":"ok"}');
        $handler = new CapturingHandler(response: $expected);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the original response is returned unchanged */
        self::assertSame($expected, $actual);
    }

    public function testProcessWhenLogErrorsDisabledAndHandlerThrowsThenNoLogging(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/users');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Should not be logged'));

        /** @And a logger that should never be called */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        /** @And a middleware with logErrors disabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: false,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testProcessWhenLoggerNotProvidedAndHandlerThrowsThenNoLogging(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Silent error'));

        /** @And a middleware built without a logger */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the process should complete returning a 500 response */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testBuildWhenMappingNotConfiguredThenMappingNotConfiguredIsThrown(): void
    {
        /** @Then MappingNotConfigured should be thrown when building without a mapping */
        $this->expectException(MappingNotConfigured::class);

        /** @When building a middleware without configuring a mapping */
        ErrorMiddleware::create()->build();
    }

    public function testProcessWhenFallbackDisabledAndMappingReturnsNullThenNoLogging(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/checkout');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Unhandled error'));

        /** @And a logger that should never be called */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        /** @And a middleware with fallback disabled and logErrors enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->withFallbackOnUnmapped(false)
            ->build();

        /** @Then the original exception should propagate without logging */
        $this->expectException(RuntimeException::class);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
    }

    public function testProcessWhenMappedErrorHasHeadersThenHeadersAreIncludedInResponse(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/sessions');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Too many attempts'));

        /** @And a mapping that returns a MappedError with a Retry-After header */
        $mapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(
                        code: 'LOGIN_THROTTLED',
                        status: 429,
                        message: 'Too many failed login attempts. Please try again later.',
                        headers: ['Retry-After' => '60']
                    );
            }
        };

        /** @And a middleware configured with the mapping */
        $middleware = ErrorMiddleware::create()->withMapping(mapping: $mapping)->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should have status 429 */
        self::assertSame(Code::TOO_MANY_REQUESTS->value, $actual->getStatusCode());

        /** @And the Retry-After header should be included in the response */
        self::assertSame('60', $actual->getHeaderLine('Retry-After'));
    }

    public function testProcessWhenHandlerThrowsAndCorrelationIdPresentThenLoggerIsCorrelated(): void
    {
        /** @Given a request with a correlation ID attribute */
        $correlationId = new readonly class implements CorrelationId {
            public function toString(): string
            {
                return '550e8400-e29b-41d4-a716-446655440000';
            }
        };
        $request = new ServerRequest('GET', '/not-found')
            ->withAttribute('correlationId', $correlationId);

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Resource not found';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects the correlated error call */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'error',
                'error',
                self::callback(function (array $context) use ($exceptionMessage): bool {
                    return $context['message'] === $exceptionMessage
                        && isset($context['correlation_id'])
                        && !isset($context['exception']);
                })
            );

        /** @And a middleware with logErrors enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testProcessWhenLogErrorDetailsEnabledThenLogContextContainsExceptionDetails(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/payments');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Payment gateway timeout';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects the error key with full details in context */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'error',
                self::callback(function (array $context) use ($exceptionMessage): bool {
                    return $context['message'] === $exceptionMessage
                        && isset($context['exception'], $context['file'], $context['line'], $context['trace'])
                        && $context['exception'] === Exception::class
                        && is_string($context['file'])
                        && is_int($context['line'])
                        && is_string($context['trace']);
                })
            );

        /** @And a middleware with logErrors and logErrorDetails enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: true,
                displayErrorDetails: false
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testProcessWhenDisplayErrorDetailsTrueThenFallbackBodyContainsExceptionDetails(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/orders');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Order processing failed'));

        /** @And a middleware with displayErrorDetails enabled */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: false,
                logErrorDetails: false,
                displayErrorDetails: true
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());

        /** @And the body should contain the fallback code and message */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame('INTERNAL_ERROR', $body['code']);
        self::assertSame('An unexpected error occurred.', $body['message']);

        /** @And the body should contain exception detail fields */
        self::assertSame(Exception::class, $body['exception']);
        self::assertArrayHasKey('file', $body);
        self::assertArrayHasKey('line', $body);
        self::assertIsArray($body['trace']);
        self::assertNotEmpty($body['trace']);
    }

    public function testProcessWhenMappedErrorHasMultiValueHeaderThenAllValuesAreIncludedInResponse(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/sessions');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Too many attempts'));

        /** @And a mapping that returns a MappedError with a multi-value header */
        $mapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(
                        code: 'ERR',
                        status: 429,
                        message: 'An error occurred.',
                        headers: ['X-Custom' => ['a', 'b']]
                    );
            }
        };

        /** @And a middleware configured with the mapping */
        $middleware = ErrorMiddleware::create()->withMapping(mapping: $mapping)->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should have status 429 */
        self::assertSame(Code::TOO_MANY_REQUESTS->value, $actual->getStatusCode());

        /** @And the multi-value header should be present in the response */
        self::assertSame(['a', 'b'], $actual->getHeader('X-Custom'));
    }

    public function testProcessWhenMappingsComposeAndALaterMappingMatchesThenItResolvesTheException(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/orders');

        /** @And a handler that throws a logic exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new LogicException('Order context invalid'));

        /** @And a write-side mapping that only translates runtime failures */
        $writeMapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(code: 'RUNTIME_FAILURE', status: 409, message: 'A runtime failure occurred.');
            }
        };

        /** @And a read-side mapping that translates logic failures */
        $readMapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: LogicException::class)
                    ->mapsTo(code: 'INVALID_CONTEXT', status: 422, message: 'The order context is invalid.');
            }
        };

        /** @And a middleware composing both mappings */
        $middleware = ErrorMiddleware::create()->withMappings($writeMapping, $readMapping)->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the later mapping resolves the exception */
        self::assertSame(Code::UNPROCESSABLE_ENTITY->value, $actual->getStatusCode());

        /** @And the body carries the later mapping's code */
        self::assertSame('INVALID_CONTEXT', json_decode((string)$actual->getBody(), true)['code']);
    }

    public function testProcessWhenLoggerProvidedAndLogErrorsEnabledAndHandlerThrowsThenErrorIsLogged(): void
    {
        /** @Given a request */
        $request = new ServerRequest('DELETE', '/account');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Critical system failure';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects an error call with the exception message in context */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'error',
                self::callback(function (array $context) use ($exceptionMessage): bool {
                    return $context['message'] === $exceptionMessage
                        && !isset($context['exception']);
                })
            );

        /** @And a middleware with logErrors enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: new UnmappedExceptions())
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testProcessWhenMappingReturnsNullAndFallbackEnabledThenFallbackResponseIsReturned(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/checkout');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Unexpected database error'));

        /** @And a middleware with default settings (fallback enabled) */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());

        /** @And the body should contain the INTERNAL_ERROR code and message */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame('INTERNAL_ERROR', $body['code']);
        self::assertSame('An unexpected error occurred.', $body['message']);

        /** @And the body should not contain exception details */
        self::assertArrayNotHasKey('exception', $body);
        self::assertArrayNotHasKey('file', $body);
        self::assertArrayNotHasKey('line', $body);
        self::assertArrayNotHasKey('trace', $body);
    }

    public function testProcessWhenHandlerThrowsAndMappingResolvesAndLogErrorsEnabledThenErrorIsLogged(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/transactions');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Transaction failed';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException($exceptionMessage));

        /** @And a mapping that maps the exception to a 422 response */
        $mapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(
                        code: 'TRANSACTION_FAILED',
                        status: 422,
                        message: 'The transaction could not be processed.'
                    );
            }
        };

        /** @And a logger that expects exactly one error call */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'error',
                self::callback(function (array $context) use ($exceptionMessage): bool {
                    return $context['message'] === $exceptionMessage;
                })
            );

        /** @And a middleware with logErrors enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: $mapping)
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should have the mapped status code */
        self::assertSame(Code::UNPROCESSABLE_ENTITY->value, $actual->getStatusCode());
    }

    public function testProcessWhenFallbackDisabledAndMappingResolvesAndLogErrorsEnabledThenErrorIsLogged(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/config');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Config update failed';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException($exceptionMessage));

        /** @And a mapping that maps the exception */
        $mapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(code: 'CONFIG_INVALID', status: 400, message: 'The configuration is invalid.');
            }
        };

        /** @And a logger that expects exactly one error call */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'error',
                self::callback(function (array $context) use ($exceptionMessage): bool {
                    return $context['message'] === $exceptionMessage;
                })
            );

        /** @And a middleware with fallback disabled but mapping configured */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withMapping(mapping: $mapping)
            ->withSettings(settings: ErrorHandlingSettings::from(
                logErrors: true,
                logErrorDetails: false,
                displayErrorDetails: false
            ))
            ->withFallbackOnUnmapped(false)
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the mapped response is returned (mapping resolved, so no rethrow) */
        self::assertSame(Code::BAD_REQUEST->value, $actual->getStatusCode());
    }

    public function testProcessWhenHandlerThrowsAndMappingResolvesToMappedErrorThenMappedResponseIsReturned(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/orders');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Order not found'));

        /** @And a mapping that maps the exception to a 404 response */
        $mapping = new class implements ExceptionMapping {
            public function mappings(): ExceptionMappingTable
            {
                return ExceptionMappingTable::create()
                    ->when(exceptionClass: RuntimeException::class)
                    ->mapsTo(code: 'ORDER_NOT_FOUND', status: 404, message: 'The requested order was not found.');
            }
        };

        /** @And a middleware configured with the mapping */
        $middleware = ErrorMiddleware::create()->withMapping(mapping: $mapping)->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should have the mapped status code */
        self::assertSame(Code::NOT_FOUND->value, $actual->getStatusCode());

        /** @And the body should contain the mapped code and message */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame('ORDER_NOT_FOUND', $body['code']);

        /** @And the body should contain the mapped message */
        self::assertSame('The requested order was not found.', $body['message']);

        /** @And the body should not contain fallback keys */
        self::assertArrayNotHasKey('exception', $body);
    }

    public function testProcessWhenHandlerThrowsAndMappingReturnsNullAndFallbackDisabledThenExceptionPropagates(): void
    {
        /** @Given a request */
        $request = new ServerRequest('DELETE', '/account');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Cannot delete account'));

        /** @And a middleware with fallback disabled */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withFallbackOnUnmapped(false)
            ->build();

        /** @Then the original exception should propagate */
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete account');

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
    }
}
