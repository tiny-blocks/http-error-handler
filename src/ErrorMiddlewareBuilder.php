<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\Exceptions\MappingNotConfigured;

/**
 * Fluent builder that assembles an {@see ErrorMiddleware} from a logger, exception mappings, error reporters,
 * settings, and the rules deciding the message a response answers with and the priority an error carries.
 */
interface ErrorMiddlewareBuilder
{
    /**
     * Builds the configured ErrorMiddleware.
     *
     * @return ErrorMiddleware The configured middleware instance.
     * @throws MappingNotConfigured If no exception mapping was configured.
     */
    public function build(): ErrorMiddleware;

    /**
     * Returns a builder configured with the given logger.
     *
     * @param LoggerInterface|null $logger The logger to use for error logging, or <code>null</code> to disable logging.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withLogger(?LoggerInterface $logger): ErrorMiddlewareBuilder;

    /**
     * Returns a builder with the given exception mapping registered.
     *
     * @param ExceptionMapping $mapping The exception mapping to register.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withMapping(ExceptionMapping $mapping): ErrorMiddlewareBuilder;

    /**
     * Registers the rule that settles the message the response answers with.
     *
     * <p>Without this rule the response answers what the mapping or the fallback already said. An
     * application that speaks to people replaces it, and decides from the resolved code and the request,
     * so the message is settled once and never parsed back out of a rendered body.</p>
     *
     * @param Closure(ErrorPayload, ServerRequestInterface): string $resolver The rule naming the message to answer
     *                                                                       with.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withMessage(Closure $resolver): ErrorMiddlewareBuilder;

    /**
     * Returns a builder with the given exception mappings registered.
     *
     * @param ExceptionMapping ...$mappings The exception mappings to register.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withMappings(ExceptionMapping ...$mappings): ErrorMiddlewareBuilder;

    /**
     * Registers the rule that derives a priority from the status the middleware resolved.
     *
     * <p>The default is {@see ReportingPriority::from}, which every consumer is free to replace. What an
     * exception declares through {@see PrioritizedError} still wins over whatever this rule answers.</p>
     *
     * @param Closure(?MappedError, Code): ReportingPriority $resolver The rule naming the priority an error
     *                                                              justifies.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withPriority(Closure $resolver): ErrorMiddlewareBuilder;

    /**
     * Returns a builder with the given error reporter registered.
     *
     * <p>Reporters are optional, and the method may be called more than once to register several of
     * them, so one application can report to an error tracking service, a metrics backend, and an
     * audit trail at once. Each one is notified after the response has been produced and the log
     * entry emitted, and a failure thrown by one of them is discarded, so it can never affect the
     * response.</p>
     *
     * @param ErrorReporter $reporter The reporter notified when an error is handled.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withReporter(ErrorReporter $reporter): ErrorMiddlewareBuilder;

    /**
     * Returns a builder configured with the given error handling settings.
     *
     * @param ErrorHandlingSettings $settings The settings controlling error display and logging behavior.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withSettings(ErrorHandlingSettings $settings): ErrorMiddlewareBuilder;

    /**
     * Returns a builder configured with the fallback behavior for unmapped exceptions.
     *
     * @param bool $fallbackOnUnmapped Whether to return a fallback response when no mapping matches.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withFallbackOnUnmapped(bool $fallbackOnUnmapped): ErrorMiddlewareBuilder;
}
