<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Throwable;

final readonly class ExceptionDetails
{
    private function __construct(private string|array $trace, private Throwable $exception)
    {
    }

    public static function withTraceLines(Throwable $exception): ExceptionDetails
    {
        return new ExceptionDetails(trace: explode("\n", $exception->getTraceAsString()), exception: $exception);
    }

    public static function withInlineTrace(Throwable $exception): ExceptionDetails
    {
        return new ExceptionDetails(trace: $exception->getTraceAsString(), exception: $exception);
    }

    public function toArray(): array
    {
        return [
            'exception' => $this->exception::class,
            'file'      => $this->exception->getFile(),
            'line'      => $this->exception->getLine(),
            'trace'     => $this->trace
        ];
    }
}
