<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Reporting;

use Sentry\State\Scope;
use TinyBlocks\Http\ErrorHandler\ReportedError;
use TinyBlocks\Http\ErrorHandler\ReportingContext;

final readonly class SentryTags
{
    public static function applyTo(ReportedError $error, Scope $scope): void
    {
        $exception = $error->exception;
        $declared = $exception instanceof ReportingContext ? $exception->reportingTags() : [];

        $tags = [
            'error_mapped' => $error->wasMapped() ? 'true' : 'false',
            'status_code'  => (string)$error->status->value
        ];

        if (!is_null($error->mapped)) {
            $tags['error_code'] = $error->mapped->code;
        }

        if (!is_null($error->correlationId)) {
            $tags['correlation_id'] = $error->correlationId->toString();
        }

        foreach ([...$tags, ...$error->tags, ...$declared] as $key => $value) {
            $scope->setTag(key: $key, value: $value);
        }
    }
}
