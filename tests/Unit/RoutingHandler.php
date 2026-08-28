<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

final readonly class RoutingHandler implements RequestHandlerInterface
{
    public function __construct(private RequestHandlerInterface $handler, private RouteMiddleware $middleware)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->handler);
    }
}
