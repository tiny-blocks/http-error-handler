<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

final readonly class SequencedReporter implements ErrorReporter
{
    public function __construct(private CallSequence $sequence)
    {
    }

    public function report(ReportedError $error): void
    {
        $this->sequence->record(name: 'report');
    }
}
