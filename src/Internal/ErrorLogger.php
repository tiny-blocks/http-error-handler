<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\CorrelationId\CorrelatedLogger;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final readonly class ErrorLogger
{
    private function __construct(private ?LoggerInterface $logger, private ErrorHandlingSettings $settings)
    {
    }

    public static function from(?LoggerInterface $logger, ErrorHandlingSettings $settings): ErrorLogger
    {
        return new ErrorLogger(logger: $logger, settings: $settings);
    }

    public function log(ReportedError $error): void
    {
        if (is_null($this->logger) || !$this->settings->logErrors) {
            return;
        }

        $exception = $error->exception;
        $logger = is_null($error->correlationId)
            ? $this->logger
            : CorrelatedLogger::from(logger: $this->logger, correlationId: $error->correlationId);

        $context = ['message' => $exception->getMessage()];

        if ($this->settings->logErrorDetails) {
            $context = [...$context, ...ExceptionDetails::withInlineTrace(exception: $exception)->toArray()];
        }

        $error->status->isClientError()
            ? $logger->warning('error', $context)
            : $logger->error('error', $context);
    }
}
