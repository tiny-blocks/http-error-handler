<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Reporting;

use Sentry\Severity;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;

final readonly class SentrySeverity
{
    public static function from(ReportingPriority $priority): Severity
    {
        return match ($priority) {
            ReportingPriority::LOW      => Severity::info(),
            ReportingPriority::HIGH     => Severity::error(),
            ReportingPriority::MEDIUM   => Severity::warning(),
            ReportingPriority::CRITICAL => Severity::fatal()
        };
    }
}
