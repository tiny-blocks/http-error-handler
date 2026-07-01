<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ErrorHandler\MappedError;
use TinyBlocks\Http\Server\Response;

final readonly class MappedResponse
{
    private function __construct(private MappedError $mapped)
    {
    }

    public static function from(MappedError $mapped): MappedResponse
    {
        return new MappedResponse(mapped: $mapped);
    }

    public function toResponse(): ResponseInterface
    {
        $response = Response::from(
            body: ['code' => $this->mapped->code, 'message' => $this->mapped->message],
            code: Code::from($this->mapped->status)
        );

        foreach ($this->mapped->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
