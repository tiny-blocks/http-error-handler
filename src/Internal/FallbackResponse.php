<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\Server\Response;

final readonly class FallbackResponse
{
    private function __construct(private ErrorHandlingSettings $settings, private Throwable $exception)
    {
    }

    public static function from(ErrorHandlingSettings $settings, Throwable $exception): FallbackResponse
    {
        return new FallbackResponse(settings: $settings, exception: $exception);
    }

    public function toResponse(): ResponseInterface
    {
        if ($this->settings->displayErrorDetails) {
            return Response::from(
                body: [
                    'code'      => 'INTERNAL_ERROR',
                    'message'   => 'An unexpected error occurred.',
                    'exception' => $this->exception::class,
                    'file'      => $this->exception->getFile(),
                    'line'      => $this->exception->getLine(),
                    'trace'     => explode("\n", $this->exception->getTraceAsString())
                ],
                code: Code::INTERNAL_SERVER_ERROR
            );
        }

        return Response::from(
            body: ['code' => 'INTERNAL_ERROR', 'message' => 'An unexpected error occurred.'],
            code: Code::INTERNAL_SERVER_ERROR
        );
    }
}
