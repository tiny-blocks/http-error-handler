<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TinyBlocks\Http\ErrorHandler\Internal\DefaultErrorMiddlewareBuilder;
use TinyBlocks\Http\ErrorHandler\Internal\ErrorLogger;
use TinyBlocks\Http\ErrorHandler\Internal\ErrorOutcome;

/**
 * PSR-15 middleware that captures exceptions thrown downstream, delegates to a consumer-provided
 * {@see ExceptionMapping}, and produces a structured JSON error response. Unmapped exceptions either
 * fall back to a 500 response or rethrow, depending on the builder configuration.
 */
final readonly class ErrorMiddleware implements MiddlewareInterface
{
    private function __construct(private ErrorOutcome $errorOutcome)
    {
    }

    /**
     * Builds an ErrorMiddleware from its configuration components.
     *
     * @param LoggerInterface|null $logger The logger to use for error logging, or <code>null</code> to disable logging.
     * @param ExceptionMappingTable $mappings The composed table that maps exceptions to error responses.
     * @param ErrorHandlingSettings $settings The settings controlling error display and logging behavior.
     * @param bool $fallbackOnUnmapped Whether to return a fallback response when no mapping matches.
     * @return ErrorMiddleware The configured middleware instance.
     */
    public static function build(
        ?LoggerInterface $logger,
        ExceptionMappingTable $mappings,
        ErrorHandlingSettings $settings,
        bool $fallbackOnUnmapped
    ): ErrorMiddleware {
        $errorOutcome = new ErrorOutcome(
            mappings: $mappings,
            settings: $settings,
            errorLogger: ErrorLogger::from(logger: $logger, settings: $settings),
            fallbackOnUnmapped: $fallbackOnUnmapped
        );

        return new ErrorMiddleware(errorOutcome: $errorOutcome);
    }

    /**
     * Creates an ErrorMiddlewareBuilder for fluent configuration.
     *
     * @return ErrorMiddlewareBuilder The builder instance.
     */
    public static function create(): ErrorMiddlewareBuilder
    {
        return new DefaultErrorMiddlewareBuilder();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return $this->errorOutcome->resolve(request: $request, exception: $exception);
        }
    }
}
