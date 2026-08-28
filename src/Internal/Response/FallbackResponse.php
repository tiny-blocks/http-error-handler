<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\Internal\ExceptionDetails;
use TinyBlocks\Http\Server\Response;

final readonly class FallbackResponse implements ErrorResponse
{
    private const string CODE = 'INTERNAL_ERROR';

    private const string MESSAGE = 'An unexpected error occurred.';

    private function __construct(private ErrorHandlingSettings $settings, private Throwable $exception)
    {
    }

    public static function from(ErrorHandlingSettings $settings, Throwable $exception): FallbackResponse
    {
        return new FallbackResponse(settings: $settings, exception: $exception);
    }

    public function payload(): ErrorPayload
    {
        $clientError = ClientErrorStatus::from(exception: $this->exception);

        return is_null($clientError)
            ? new ErrorPayload(
                code: self::CODE,
                status: Code::INTERNAL_SERVER_ERROR,
                message: self::MESSAGE,
                wasMapped: false
            )
            : new ErrorPayload(
                code: $clientError->name,
                status: $clientError,
                message: sprintf('%s.', $clientError->message()),
                wasMapped: false
            );
    }

    public function toResponse(string $message): ResponseInterface
    {
        $payload = $this->payload();
        $body = ['code' => $payload->code, 'message' => $message];

        if ($payload->status->isServerError() && $this->settings->displayErrorDetails) {
            $body = [...$body, ...ExceptionDetails::withTraceLines(exception: $this->exception)->toArray()];
        }

        return Response::from(body: $body, code: $payload->status);
    }
}
