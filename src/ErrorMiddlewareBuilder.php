<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\ErrorHandler\Exceptions\MappingNotConfigured;

/**
 * Fluent builder that assembles an {@see ErrorMiddleware} from a logger, exception mappings, and settings.
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
     * Returns a builder with the given exception mappings registered.
     *
     * @param ExceptionMapping ...$mappings The exception mappings to register.
     * @return ErrorMiddlewareBuilder The configured builder.
     */
    public function withMappings(ExceptionMapping ...$mappings): ErrorMiddlewareBuilder;

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
