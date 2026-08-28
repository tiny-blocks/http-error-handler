<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Reporting;

use Sentry\Event;
use Sentry\State\Scope;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final readonly class SentryScope
{
    public static function apply(ReportedError $error, Scope $scope): void
    {
        $scope->setLevel(level: SentrySeverity::from(priority: $error->priority));
        $scope->setContext(name: 'http', value: ['method' => $error->method, 'path' => $error->path]);

        SentryTags::applyTo(error: $error, scope: $scope);

        if (!is_null($error->route)) {
            $scope->addEventProcessor(
                eventProcessor: static fn(Event $event): Event => $event->setTransaction(transaction: $error->route)
            );
        }
    }
}
