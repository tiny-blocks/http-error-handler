<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\Internal\ResolvedRoute;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

final class RouteMiddlewareTest extends TestCase
{
    public function testProcessWhenNoCarrierIsOnTheRequestThenTheRequestStillReachesTheHandler(): void
    {
        /** @Given a request no error middleware prepared, so it carries no place to write the route */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a middleware that names whatever it matches */
        $middleware = RouteMiddleware::resolvedBy(
            resolver: static fn(ServerRequestInterface $request): string => '/v1/orders'
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, new CapturingHandler());

        /** @Then the request reaches the handler untouched, because naming is not the point of the stack */
        self::assertSame(Code::OK->value, $actual->getStatusCode());
    }

    public function testProcessWhenTheCarrierAttributeHoldsForeignValueThenTheRequestStillReachesTheHandler(): void
    {
        /** @Given a request whose carrier attribute holds something that is not a carrier */
        $request = new ServerRequest('GET', '/v1/orders')
            ->withAttribute(ResolvedRoute::ATTRIBUTE_NAME, 'not-a-carrier');

        /** @And a middleware that names whatever it matches */
        $middleware = RouteMiddleware::resolvedBy(
            resolver: static fn(ServerRequestInterface $request): string => '/v1/orders'
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, new CapturingHandler());

        /** @Then the foreign value is refused instead of written to, and the request still flows */
        self::assertSame(Code::OK->value, $actual->getStatusCode());
    }
}
