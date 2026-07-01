<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorMiddlewareBuilder;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\Exceptions\MappingNotConfigured;

final class DefaultErrorMiddlewareBuilder implements ErrorMiddlewareBuilder
{
    private bool $hasMapping = false;
    private ?LoggerInterface $logger = null;
    private ExceptionMappingTable $mappings;
    private ErrorHandlingSettings $settings;
    private bool $fallbackOnUnmapped = true;

    public function __construct()
    {
        $this->mappings = ExceptionMappingTable::create();
        $this->settings = ErrorHandlingSettings::default();
    }

    public function build(): ErrorMiddleware
    {
        if (!$this->hasMapping) {
            throw new MappingNotConfigured();
        }

        return ErrorMiddleware::build(
            logger: $this->logger,
            mappings: $this->mappings,
            settings: $this->settings,
            fallbackOnUnmapped: $this->fallbackOnUnmapped
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

    public function withMappings(ExceptionMapping ...$mappings): ErrorMiddlewareBuilder
    {
        foreach ($mappings as $mapping) {
            $this->withMapping(mapping: $mapping);
        }

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
