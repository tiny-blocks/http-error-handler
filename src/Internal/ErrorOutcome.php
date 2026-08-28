<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\Internal\Response\FallbackResponse;
use TinyBlocks\Http\ErrorHandler\Internal\Response\MappedResponse;

final readonly class ErrorOutcome
{
    public function __construct(
        private ExceptionMappingTable $mappings,
        private ErrorHandlingSettings $settings,
        private ErrorLogger $errorLogger,
        private bool $fallbackOnUnmapped
    ) {
    }

    public function resolve(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $mapped = $this->mappings->mapTo(exception: $exception);

        if (is_null($mapped) && !$this->fallbackOnUnmapped) {
            throw $exception;
        }

        $response = is_null($mapped)
            ? FallbackResponse::from(settings: $this->settings, exception: $exception)->toResponse()
            : MappedResponse::from(mapped: $mapped)->toResponse();

        $this->errorLogger->log(
            status: $response->getStatusCode(),
            request: $request,
            exception: $exception
        );

        return $response;
    }
}
