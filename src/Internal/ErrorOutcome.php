<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\Internal\Response\FallbackResponse;
use TinyBlocks\Http\ErrorHandler\Internal\Response\MappedResponse;
use TinyBlocks\Http\ErrorHandler\MappedError;
use TinyBlocks\Http\ErrorHandler\PrioritizedError;
use TinyBlocks\Http\ErrorHandler\ReportedError;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;
use TinyBlocks\Http\ErrorHandler\ReportingTags;

final readonly class ErrorOutcome
{
    /**
     * @param Closure(ErrorPayload, ServerRequestInterface): string $message
     * @param Closure(?MappedError, Code): ReportingPriority $priority
     */
    public function __construct(
        private Closure $message,
        private ExceptionMappingTable $mappings,
        private Closure $priority,
        private ErrorHandlingSettings $settings,
        private ErrorLogger $errorLogger,
        private ErrorReporter $errorReporter,
        private bool $fallbackOnUnmapped
    ) {
    }

    public function resolve(
        ReportingTags $tags,
        ResolvedRoute $route,
        ServerRequestInterface $request,
        Throwable $exception
    ): ResponseInterface {
        $mapped = $this->mappings->mapTo(exception: $exception);

        if (is_null($mapped) && !$this->fallbackOnUnmapped) {
            throw $exception;
        }

        $errorResponse = is_null($mapped)
            ? FallbackResponse::from(settings: $this->settings, exception: $exception)
            : MappedResponse::from(mapped: $mapped);

        $payload = $errorResponse->payload();
        $response = $errorResponse->toResponse(message: ($this->message)($payload, $request));

        $priority = $exception instanceof PrioritizedError
            ? $exception->reportingPriority()
            : ($this->priority)($mapped, $payload->status);

        $error = new ReportedError(
            path: $request->getUri()->getPath(),
            method: $request->getMethod(),
            status: $payload->status,
            priority: $priority,
            exception: $exception,
            tags: $tags->toArray(),
            route: $route->pattern(),
            mapped: $mapped,
            correlationId: RequestCorrelationId::from(request: $request)
        );

        $this->errorLogger->log(error: $error);
        $this->errorReporter->report($error);

        return $response;
    }
}
