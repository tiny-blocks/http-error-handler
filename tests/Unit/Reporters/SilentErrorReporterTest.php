<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit\Reporters;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Test\TinyBlocks\Http\ErrorHandler\Unit\RecordingReporter;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnmappedExceptions;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\Reporters\SilentErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;

final class SilentErrorReporterTest extends TestCase
{
    public function testBuildWhenNoReporterIsGivenThenTheSilentReporterTakesTheReport(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a middleware assembled from its components, with no reporter named */
        $middleware = ErrorMiddleware::build(
            logger: null,
            message: static fn(ErrorPayload $payload): string => $payload->message,
            priority: ReportingPriority::from(...),
            mappings: new UnmappedExceptions()->mappings(),
            settings: ErrorHandlingSettings::default(),
            fallbackOnUnmapped: true
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the report went nowhere and the response is the one the fallback produces */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testReportWhenTheSilentReporterIsRegisteredThenTheRemainingOnesStillReceiveIt(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new Exception('Boom'));

        /** @And a reporter that records what it receives */
        $reporter = new RecordingReporter();

        /** @And a middleware where the silent reporter stands for a provider switched off */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->withReporter(reporter: new SilentErrorReporter())
            ->withReporter(reporter: $reporter)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the silent reporter forwarded nothing and the provider still received the report */
        self::assertNotNull($reporter->reported);
    }
}
