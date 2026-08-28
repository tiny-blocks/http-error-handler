<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Closure;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;

final class ErrorMessageTest extends TestCase
{
    private function middlewareAnswering(ExceptionMapping $mapping, Closure $resolver): ErrorMiddleware
    {
        return ErrorMiddleware::create()
            ->withMapping(mapping: $mapping)
            ->withMessage(resolver: $resolver)
            ->withSettings(settings: ErrorHandlingSettings::default())
            ->build();
    }

    private function spellingOutThePayload(): Closure
    {
        return static fn(ErrorPayload $payload, ServerRequestInterface $request): string => sprintf(
            '%s|%d|%s',
            $payload->code,
            $payload->status->value,
            $payload->wasMapped ? 'described' : 'undescribed'
        );
    }

    public function testResolverReadsTheRequestItAnswers(): void
    {
        /** @Given a request asking for Portuguese */
        $request = new ServerRequest('GET', '/v1/orders', ['Accept-Language' => 'pt-BR']);

        /** @And a handler that throws */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream is down.'));

        /** @And a middleware answering in the language the request asked for */
        $middleware = $this->middlewareAnswering(
            mapping: new UnavailableExceptions(),
            resolver: static fn(ErrorPayload $payload, ServerRequestInterface $request): string
                => $request->getHeaderLine('Accept-Language') === 'pt-BR'
                    ? 'O serviço está indisponível.'
                    : $payload->message
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the answer is in the language the request asked for */
        self::assertJsonStringEqualsJsonString(
            '{"code":"UPSTREAM_UNAVAILABLE","message":"O serviço está indisponível."}',
            (string)$actual->getBody()
        );
    }

    public function testMappedMessageIsReplacedByTheResolver(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing an exception a rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Upstream is down.'));

        /** @And a middleware whose resolver spells out what the payload carries */
        $middleware = $this->middlewareAnswering(
            mapping: new UnavailableExceptions(),
            resolver: $this->spellingOutThePayload()
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the resolved code and status reached the resolver, and only the message changed */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $actual->getStatusCode());
        self::assertSame(
            '{"code":"UPSTREAM_UNAVAILABLE","message":"UPSTREAM_UNAVAILABLE|503|described"}',
            (string)$actual->getBody()
        );
    }

    public function testFallbackMessageIsReplacedByTheResolver(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing an exception no rule describes */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('Boom'));

        /** @And a middleware whose resolver spells out what the payload carries */
        $middleware = $this->middlewareAnswering(
            mapping: new UnmappedExceptions(),
            resolver: $this->spellingOutThePayload()
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the fallback code and status reached the resolver, and only the message changed */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
        self::assertSame(
            '{"code":"INTERNAL_ERROR","message":"INTERNAL_ERROR|500|undescribed"}',
            (string)$actual->getBody()
        );
    }

    public function testUnmappedClientErrorCarriesTheStatusNameAsCode(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/v1/orders');

        /** @And a handler throwing an undescribed exception that declares a client status */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(
            new RuntimeException('Not found.', Code::NOT_FOUND->value)
        );

        /** @And a middleware whose resolver spells out what the payload carries */
        $middleware = $this->middlewareAnswering(
            mapping: new UnmappedExceptions(),
            resolver: $this->spellingOutThePayload()
        );

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the code is the name of the status the exception declared */
        self::assertSame(Code::NOT_FOUND->value, $actual->getStatusCode());
        self::assertSame(
            '{"code":"NOT_FOUND","message":"NOT_FOUND|404|undescribed"}',
            (string)$actual->getBody()
        );
    }
}
