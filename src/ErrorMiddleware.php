<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\Internal\DefaultErrorMiddlewareBuilder;
use TinyBlocks\Http\ErrorHandler\Internal\ErrorLogger;
use TinyBlocks\Http\ErrorHandler\Internal\ErrorOutcome;
use TinyBlocks\Http\ErrorHandler\Internal\Reporting\CompositeErrorReporter;
use TinyBlocks\Http\ErrorHandler\Internal\ResolvedRoute;
use TinyBlocks\Http\ErrorHandler\Reporters\SilentErrorReporter;

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
     * @param Closure(ErrorPayload, ServerRequestInterface): string $message The rule settling the message the
     *                                                            response answers with.
     * @param ExceptionMappingTable $mappings The composed table that maps exceptions to error responses.
     * @param Closure(?MappedError, Code): ReportingPriority $priority The rule deriving a priority from the rule
     *                                                            that matched and the resolved status.
     * @param ErrorHandlingSettings $settings The settings controlling error display and logging behavior.
     * @param bool $fallbackOnUnmapped Whether to return a fallback response when no mapping matches.
     * @param ErrorReporter $reporter The reporter notified after the response is produced and logged. Defaults to
     *                                {@see SilentErrorReporter}, which forwards every report to no provider.
     * @return ErrorMiddleware The configured middleware instance.
     */
    public static function build(
        ?LoggerInterface $logger,
        Closure $message,
        ExceptionMappingTable $mappings,
        Closure $priority,
        ErrorHandlingSettings $settings,
        bool $fallbackOnUnmapped,
        ErrorReporter $reporter = new SilentErrorReporter()
    ): ErrorMiddleware {
        $errorOutcome = new ErrorOutcome(
            message: $message,
            mappings: $mappings,
            priority: $priority,
            settings: $settings,
            errorLogger: ErrorLogger::from(logger: $logger, settings: $settings),
            errorReporter: CompositeErrorReporter::create()->with(reporter: $reporter),
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
        $tags = ReportingTags::pending();
        $route = ResolvedRoute::pending();
        $routed = $request
            ->withAttribute(ResolvedRoute::ATTRIBUTE_NAME, $route)
            ->withAttribute(ReportingTags::ATTRIBUTE_NAME, $tags);

        try {
            return $handler->handle($routed);
        } catch (Throwable $exception) {
            return $this->errorOutcome->resolve(
                tags: $tags,
                route: $route,
                request: $routed,
                exception: $exception
            );
        }
    }
}
