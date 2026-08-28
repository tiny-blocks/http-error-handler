<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Closure;
use Psr\Log\LoggerInterface;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorMiddlewareBuilder;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\Exceptions\MappingNotConfigured;
use TinyBlocks\Http\ErrorHandler\Internal\Reporting\CompositeErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;

final class DefaultErrorMiddlewareBuilder implements ErrorMiddlewareBuilder
{
    private Closure $message;
    private Closure $priority;
    private bool $hasMapping = false;
    private ?LoggerInterface $logger = null;
    private ExceptionMappingTable $mappings;
    private CompositeErrorReporter $reporter;
    private ErrorHandlingSettings $settings;
    private bool $fallbackOnUnmapped = true;

    public function __construct()
    {
        $this->message = static fn(ErrorPayload $payload): string => $payload->message;
        $this->priority = ReportingPriority::from(...);
        $this->mappings = ExceptionMappingTable::create();
        $this->reporter = CompositeErrorReporter::create();
        $this->settings = ErrorHandlingSettings::default();
    }

    public function build(): ErrorMiddleware
    {
        if (!$this->hasMapping) {
            throw new MappingNotConfigured(
                message: 'No exception mapping was registered. Call withMapping or withMappings before building.'
            );
        }

        return ErrorMiddleware::build(
            logger: $this->logger,
            message: $this->message,
            mappings: $this->mappings,
            priority: $this->priority,
            settings: $this->settings,
            fallbackOnUnmapped: $this->fallbackOnUnmapped,
            reporter: $this->reporter
        );
    }

    public function withLogger(?LoggerInterface $logger): ErrorMiddlewareBuilder
    {
        $this->logger = $logger;

        return $this;
    }

    public function withMapping(ExceptionMapping $mapping): ErrorMiddlewareBuilder
    {
        $this->mappings = $this->mappings->mergedWith(other: $mapping->mappings());
        $this->hasMapping = true;

        return $this;
    }

    public function withMessage(Closure $resolver): ErrorMiddlewareBuilder
    {
        $this->message = $resolver;

        return $this;
    }

    public function withMappings(ExceptionMapping ...$mappings): ErrorMiddlewareBuilder
    {
        foreach ($mappings as $mapping) {
            $this->withMapping(mapping: $mapping);
        }

        return $this;
    }

    public function withPriority(Closure $resolver): ErrorMiddlewareBuilder
    {
        $this->priority = $resolver;

        return $this;
    }

    public function withReporter(ErrorReporter $reporter): ErrorMiddlewareBuilder
    {
        $this->reporter = $this->reporter->with(reporter: $reporter);

        return $this;
    }

    public function withSettings(ErrorHandlingSettings $settings): ErrorMiddlewareBuilder
    {
        $this->settings = $settings;

        return $this;
    }

    public function withFallbackOnUnmapped(bool $fallbackOnUnmapped): ErrorMiddlewareBuilder
    {
        $this->fallbackOnUnmapped = $fallbackOnUnmapped;

        return $this;
    }
}
