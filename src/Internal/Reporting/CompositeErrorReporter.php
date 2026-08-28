<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Reporting;

use Throwable;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final readonly class CompositeErrorReporter implements ErrorReporter
{
    private function __construct(private array $reporters)
    {
    }

    public static function create(): CompositeErrorReporter
    {
        return new CompositeErrorReporter(reporters: []);
    }

    public function with(ErrorReporter $reporter): CompositeErrorReporter
    {
        return new CompositeErrorReporter(reporters: [...$this->reporters, $reporter]);
    }

    public function report(ReportedError $error): void
    {
        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($error);
            } catch (Throwable) {
                continue;
            }
        }
    }
}
