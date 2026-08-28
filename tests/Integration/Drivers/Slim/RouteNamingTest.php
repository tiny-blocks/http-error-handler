<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Integration\Drivers\Slim;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;
use Test\TinyBlocks\Http\ErrorHandler\Unit\RecordingReporter;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnmappedExceptions;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

final class RouteNamingTest extends TestCase
{
    public function testHandleWhenTheRouteMatchesThenTheReportCarriesThePattern(): void
    {
        /** @Given an application whose route carries an identifier in the path */
        $application = AppFactory::create();

        /** @And a reporter that records what it receives */
        $reporter = new RecordingReporter();

        /** @And a route naming middleware registered below the routing */
        $application->add(RouteMiddleware::resolvedBy(
            resolver: static fn(ServerRequestInterface $request): ?string
                => RouteContext::fromRequest($request)->getRoute()?->getPattern()
        ));

        /** @And the routing middleware above it */
        $application->addRoutingMiddleware();

        /** @And the error middleware above the routing, which is where it catches routing exceptions */
        $application->add(
            ErrorMiddleware::create()
                ->withMapping(mapping: new UnmappedExceptions())
                ->withReporter(reporter: $reporter)
                ->build()
        );

        /** @And a route that throws */
        $application->get('/v1/orders/{id}', static fn(): never => throw new RuntimeException('Boom'));

        /** @When the application handles a request for a concrete identifier */
        $actual = $application->handle(new ServerRequest('GET', '/v1/orders/1'));

        /** @Then the report carries the pattern, so the endpoint is one value instead of one per order */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
        self::assertNotNull($reporter->reported);
        self::assertSame('/v1/orders/{id}', $reporter->reported->route);
        self::assertSame('/v1/orders/1', $reporter->reported->path);
    }

    public function testHandleWhenNoRouteMatchesThenTheRoutingExceptionStillReachesTheErrorMiddleware(): void
    {
        /** @Given an application with the same stack */
        $application = AppFactory::create();

        /** @And a reporter that records what it receives */
        $reporter = new RecordingReporter();

        /** @And a route naming middleware registered below the routing */
        $application->add(RouteMiddleware::resolvedBy(
            resolver: static fn(ServerRequestInterface $request): ?string
                => RouteContext::fromRequest($request)->getRoute()?->getPattern()
        ));

        /** @And the routing middleware above it */
        $application->addRoutingMiddleware();

        /** @And the error middleware above the routing */
        $application->add(
            ErrorMiddleware::create()
                ->withMapping(mapping: new UnmappedExceptions())
                ->withReporter(reporter: $reporter)
                ->build()
        );

        /** @And a route registered at another path */
        $application->get('/v1/orders/{id}', static fn(): never => throw new RuntimeException('Boom'));

        /** @When the application handles a request no route matches */
        $actual = $application->handle(new ServerRequest('GET', '/v1/unknown'));

        /** @Then the routing exception is answered as a not found, which is what the ordering protects */
        self::assertSame(Code::NOT_FOUND->value, $actual->getStatusCode());
    }
}
