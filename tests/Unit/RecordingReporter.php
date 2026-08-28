<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final class RecordingReporter implements ErrorReporter
{
    public ?ReportedError $reported = null;

    public function report(ReportedError $error): void
    {
        $this->reported = $error;
    }
}
