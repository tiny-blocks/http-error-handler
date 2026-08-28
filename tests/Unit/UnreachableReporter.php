<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use RuntimeException;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final readonly class UnreachableReporter implements ErrorReporter
{
    public function report(ReportedError $error): void
    {
        $template = 'The provider refused the report for <%s>.';

        throw new RuntimeException(message: sprintf($template, $error->path));
    }
}
