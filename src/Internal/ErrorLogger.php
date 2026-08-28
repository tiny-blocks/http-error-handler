<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\CorrelationId\CorrelationId;
use TinyBlocks\Http\CorrelationId\CorrelationIdMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\Internal\Response\ClientErrorStatus;

final readonly class ErrorLogger
{
    private function __construct(private ?LoggerInterface $logger, private ErrorHandlingSettings $settings)
    {
    }

    public static function from(?LoggerInterface $logger, ErrorHandlingSettings $settings): ErrorLogger
    {
        return new ErrorLogger(logger: $logger, settings: $settings);
    }

    public function log(int $status, ServerRequestInterface $request, Throwable $exception): void
    {
        if (is_null($this->logger) || !$this->settings->logErrors) {
            return;
        }

        $correlationId = $request->getAttribute(CorrelationIdMiddleware::ATTRIBUTE_NAME);
        $logger = $correlationId instanceof CorrelationId
            ? CorrelatedLogger::from(logger: $this->logger, correlationId: $correlationId)
            : $this->logger;

        $context = ['message' => $exception->getMessage()];

        if ($this->settings->logErrorDetails) {
            $context['exception'] = $exception::class;
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
            $context['trace'] = $exception->getTraceAsString();
        }

        ClientErrorStatus::matches(status: $status)
            ? $logger->warning('error', $context)
            : $logger->error('error', $context);
    }
}
