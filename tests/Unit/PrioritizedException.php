<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use RuntimeException;
use TinyBlocks\Http\ErrorHandler\PrioritizedError;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;

final class PrioritizedException extends RuntimeException implements PrioritizedError
{
    public function __construct(private readonly ReportingPriority $priority)
    {
        parent::__construct(message: 'The upstream service is unavailable.');
    }

    public function reportingPriority(): ReportingPriority
    {
        return $this->priority;
    }
}
