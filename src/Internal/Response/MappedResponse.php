<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Response;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\MappedError;
use TinyBlocks\Http\Server\Response;

final readonly class MappedResponse implements ErrorResponse
{
    private function __construct(private MappedError $mapped)
    {
    }

    public static function from(MappedError $mapped): MappedResponse
    {
        return new MappedResponse(mapped: $mapped);
    }

    public function payload(): ErrorPayload
    {
        return new ErrorPayload(
            code: $this->mapped->code,
            status: Code::from($this->mapped->status),
            message: $this->mapped->message,
            wasMapped: true
        );
    }

    public function toResponse(string $message): ResponseInterface
    {
        $payload = $this->payload();

        $response = Response::from(body: ['code' => $payload->code, 'message' => $message], code: $payload->status);

        foreach ($this->mapped->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
