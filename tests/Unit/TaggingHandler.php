<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use TinyBlocks\Http\ErrorHandler\ReportingTags;

final readonly class TaggingHandler implements RequestHandlerInterface
{
    public function __construct(private string $key, private string $value, private Throwable $failure)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ReportingTags::from(request: $request)?->add(key: $this->key, value: $this->value);

        throw $this->failure;
    }
}
