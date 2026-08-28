<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final class RenamedParameterReporter implements ErrorReporter
{
    public ?ReportedError $reported = null;

    public function report(ReportedError $failure): void
    {
        $this->reported = $failure;
    }
}
