<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Internal\Reporting;

use Sentry\Event;

/**
 * Runs as the SDK <code>before_send</code> hook of a reporter booted by this library.
 *
 * <p>The SDK gathers the request on its own, from the globals of the process: the URL with its query
 * string, the headers, and the body a login or a sign up carries. It also carries whatever user the
 * application set. None of that is an error, and all of it can be personal data, so both leave the
 * event before it is sent. The method and the path an event needs travel in the <code>http</code>
 * context, which {@see SentryScope} writes.</p>
 */
final readonly class SentryPrivacy
{
    public static function strip(Event $event): Event
    {
        $event->setUser(user: null);
        $event->setRequest(request: []);

        return $event;
    }
}
