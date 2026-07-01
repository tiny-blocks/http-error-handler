<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CapturingHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $capturedRequest = null;

    public function __construct(private readonly ResponseInterface $response = new Response())
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->capturedRequest = $request;
        return $this->response;
    }

    public function wasInvoked(): bool
    {
        return !is_null($this->capturedRequest);
    }
}
