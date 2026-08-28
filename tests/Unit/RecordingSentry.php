<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

final class RecordingSentry implements TransportInterface
{
    public ?Event $sent = null;

    private HubInterface $hub;

    public function __construct()
    {
        $this->hub = new Hub(
            client: ClientBuilder::create(options: ['default_integrations' => false])
                ->setTransport(transport: $this)
                ->getClient()
        );
    }

    public function hub(): HubInterface
    {
        return $this->hub;
    }

    public function send(Event $event): Result
    {
        $this->sent = $event;

        return new Result(status: ResultStatus::success(), event: $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(status: ResultStatus::success());
    }
}
