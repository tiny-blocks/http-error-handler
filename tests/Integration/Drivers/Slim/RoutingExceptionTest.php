<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Integration\Drivers\Slim;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Test\TinyBlocks\Http\ErrorHandler\Unit\UnmappedExceptions;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;

final class RoutingExceptionTest extends TestCase
{
    public function testProcessWhenSlimReportsNoRouteThenNotFoundIsReturned(): void
    {
        /** @Given a request for a path no route was registered at */
        $request = new ServerRequest('GET', '/v1/unknown');

        /** @And a handler that throws the routing exception Slim raises for it */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new HttpNotFoundException($request));

        /** @And a middleware that maps none of the framework exceptions */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the caller receives the not found status instead of an internal error */
        self::assertSame(Code::NOT_FOUND->value, $actual->getStatusCode());

        /** @And the body follows the standard error envelope */
        self::assertJsonStringEqualsJsonString(
            '{"code":"NOT_FOUND","message":"Not Found."}',
            (string)$actual->getBody()
        );
    }

    public function testProcessWhenSlimReportsMethodNotAllowedThenMethodNotAllowedIsReturned(): void
    {
        /** @Given a request using a method the route does not support */
        $request = new ServerRequest('DELETE', '/v1/users');

        /** @And a handler that throws the routing exception Slim raises for it */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new HttpMethodNotAllowedException($request));

        /** @And a middleware that maps none of the framework exceptions */
        $middleware = ErrorMiddleware::create()
            ->withMapping(mapping: new UnmappedExceptions())
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the caller receives the method not allowed status instead of an internal error */
        self::assertSame(Code::METHOD_NOT_ALLOWED->value, $actual->getStatusCode());

        /** @And the body follows the standard error envelope */
        self::assertJsonStringEqualsJsonString(
            '{"code":"METHOD_NOT_ALLOWED","message":"Method Not Allowed."}',
            (string)$actual->getBody()
        );
    }
}
