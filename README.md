# Http Error Handler

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/http-error-handler/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Declaring a mapping](#declaring-a-mapping)
    + [Composing multiple mappings](#composing-multiple-mappings)
    + [Resolving the response from the exception](#resolving-the-response-from-the-exception)
    + [Unmapped exceptions that carry their own status](#unmapped-exceptions-that-carry-their-own-status)
    + [Logging and displaying error details](#logging-and-displaying-error-details)
    + [Disabling the fallback for unmapped exceptions](#disabling-the-fallback-for-unmapped-exceptions)
    + [Reporting errors to an external provider](#reporting-errors-to-an-external-provider)
    + [Reporting errors to Sentry](#reporting-errors-to-sentry)
    + [Naming the route a request matched](#naming-the-route-a-request-matched)
* [License](#license)
* [Contributing](#contributing)

## Overview

Provides a PSR-15 middleware that captures exceptions thrown by downstream handlers and translates them into structured
JSON error responses. Each consumer declares an `ExceptionMapping`, a class whose `mappings()` method returns an
`ExceptionMappingTable` that turns each known exception into a `MappedError` (machine-readable code, a known HTTP error
status, human-readable message, optional headers). The consumer declares only the rules it owns, and the middleware
composes the tables of every configured mapping into a single first-match-wins lookup, so neither the consumer nor the
middleware repeats the composition. Unmapped exceptions either short-circuit to the fallback response or rethrow,
depending on the builder configuration.

The fallback distinguishes a client mistake from a server failure. An unmapped exception that already declares a 4xx
status as its exception code is answered with that status, and every other unmapped exception is answered with a generic
`500`. This matters for framework exceptions the consumer never maps, most notably the routing exceptions a PSR-15 stack
raises for an unknown path or an unsupported method: without it, a caller typing the wrong URL is told the server
failed, and a client that retries on 5xx keeps retrying a request that can never succeed.

The library integrates with [tiny-blocks/http-correlation-id](https://github.com/tiny-blocks/http-correlation-id)
to enrich every log entry with the request's correlation identifier when one is present on the request attributes, so
error logs can be grouped across services without any extra plumbing in the consumer's log calls.

Beyond the response and the log entry, a handled error reaches whatever the consumer registered as an `ErrorReporter`.
That seam is where the library meets error tracking, and a `SentryReporter` ships with it.

## Installation

```bash
composer require tiny-blocks/http-error-handler
```

## How to use

### Declaring a mapping

A consumer declares the rules it owns by implementing `ExceptionMapping`. The `mappings()` method returns an
`ExceptionMappingTable` built once, so the same table is reused on every request rather than rebuilt per exception.
Rules are evaluated in registration order, and the first match wins. Exact-class, listed-class, and subclass matches
cover the common cases.

```php
<?php

declare(strict_types=1);

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;

final readonly class ApplicationExceptionMapping implements ExceptionMapping
{
    public function mappings(): ExceptionMappingTable
    {
        return ExceptionMappingTable::create()
            ->when(exceptionClass: InvalidArgumentException::class)
            ->mapsTo(code: 'INVALID_INPUT', status: 400, message: 'The request payload is invalid.')
            ->whenAny(exceptionClasses: [DomainException::class, RuntimeException::class])
            ->mapsTo(code: 'BUSINESS_FAILURE', status: 422, message: 'The operation could not be completed.')
            ->whenSubclassOf(baseException: RuntimeException::class)
            ->mapsTo(code: 'RUNTIME_FAMILY', status: 500, message: 'A runtime error occurred.');
    }
}
```

Register the mapping on the middleware and add it to the PSR-15 pipeline.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;

# Build the middleware with the declared mapping.
$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: new ApplicationExceptionMapping())
    ->build();
```

### Composing multiple mappings

When several verticals each own a mapping (for example, a write side and a read side), pass them all to `withMappings`.
The middleware composes them into a single first-match-wins lookup, evaluating the mappings in the order given, so the
consumer never writes the composition by hand.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;

# The mappings are supplied by the consumer.
$write = new WriteExceptionMapping();
$read = new ReadExceptionMapping();

# Compose both mappings under one middleware.
$middleware = ErrorMiddleware::create()
    ->withMappings($write, $read)
    ->build();
```

### Resolving the response from the exception

Builds the `MappedError` from the matched exception when the response depends on runtime state (for example, when the
exception carries fields that should be exposed to the client). The closure receives the matched throwable and returns a
`MappedError` built from it.

```php
<?php

declare(strict_types=1);

use RuntimeException;
use Throwable;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ExceptionMappingTable;
use TinyBlocks\Http\ErrorHandler\MappedError;

final readonly class GatewayExceptionMapping implements ExceptionMapping
{
    public function mappings(): ExceptionMappingTable
    {
        return ExceptionMappingTable::create()
            ->when(exceptionClass: RuntimeException::class)
            ->resolvesWith(
                resolver: fn(Throwable $exception): MappedError => new MappedError(
                    code: 'GATEWAY_UNAVAILABLE',
                    status: 502,
                    message: $exception->getMessage()
                )
            );
    }
}
```

### Unmapped exceptions that carry their own status

An unmapped exception whose exception code is a 4xx status is answered with that status, not with the generic 500.
Nothing is registered for this: it is how the fallback behaves. The response follows the same envelope, with the code
naming the status.

This covers the exceptions a consumer has no reason to map, because they belong to the framework rather than to the
domain. A PSR-15 routing stack raises one for an unknown path and another for an unsupported method, and both carry the
status on the exception itself.

```php
<?php

declare(strict_types=1);

use Slim\Exception\HttpNotFoundException;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;

# The mapping is supplied by the consumer and declares nothing about routing.
$mapping = /** @var ExceptionMapping */ null;

$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->build();

# A request for a path with no route reaches the middleware as HttpNotFoundException, whose code is 404.
# The response is 404 {"code":"NOT_FOUND","message":"Not Found."} instead of a 500.
```

An unmapped exception carrying a 5xx code, or a code that is not an HTTP status at all (a driver error number, for
example), stays a generic 500. Only client errors are adopted, because a server-side failure the consumer did not map is
exactly what the generic fallback is for.

### Rewriting the message the client reads

The message a mapping declares is written for whoever reads a log. An application that answers people wants another one,
in their language, and it decides from the code the middleware resolved rather than from the exception. `withMessage`
registers that rule, and it runs before the response is built, so the body is written once instead of being parsed and
re-encoded by a middleware sitting above.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorPayload;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;

# The mapping is supplied by the consumer.
$mapping = /** @var ExceptionMapping */ null;

# Answer in the reader's language, falling back to what the mapping declared.
$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->withMessage(resolver: static function (ErrorPayload $payload, ServerRequestInterface $request): string {
        $curated = Messages::curatedFor(code: $payload->code);

        return is_null($curated) ? $payload->message : $curated;
    })
    ->build();
```

The payload carries the business code, the resolved status, the message the middleware would have answered with, and
whether a rule described the exception. Returning `$payload->message` keeps the answer unchanged, which is what the
default rule does when `withMessage` is never called.

### Logging and displaying error details

Enables structured error logging and the optional inclusion of exception details in the response body. The defaults are
silent and secure: nothing is logged and no stack traces are returned to the client.

The log level follows the status of the response that is actually returned. A 4xx is logged as a warning and everything
else as an error, whether the status came from a mapping or from the fallback. A validation failure the consumer maps to
422 is a client mistake, and logging it as an error inflates every error-rate signal built on top of the logs. The
reporting priority is a separate axis and never moves this level: what deserves attention at an error tracker is not
what a log stream records.

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use TinyBlocks\Http\ErrorHandler\ErrorHandlingSettings;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;

# The logger and the mapping are supplied by the consumer.
$logger = /** @var LoggerInterface */ null;
$mapping = /** @var ExceptionMapping */ null;

# Enable error logging with full details, but keep stack traces out of the response.
$middleware = ErrorMiddleware::create()
    ->withLogger(logger: $logger)
    ->withMapping(mapping: $mapping)
    ->withSettings(settings: ErrorHandlingSettings::from(
        logErrors: true,
        logErrorDetails: true,
        displayErrorDetails: false
    ))
    ->build();
```

### Disabling the fallback for unmapped exceptions

Forces unmapped exceptions to propagate to the outer handler instead of returning the generic 500 fallback. Useful when
a higher-level error boundary should observe the original throwable.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;

# The mapping is supplied by the consumer.
$mapping = /** @var ExceptionMapping */ null;

# Disable the fallback so that unmapped exceptions rethrow.
$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->withFallbackOnUnmapped(fallbackOnUnmapped: false)
    ->build();
```

### Reporting errors to an external provider

Forwards every handled error to one or more consumer-provided `ErrorReporter` implementations, so an error tracking
service, a metrics backend, or an audit trail can observe what the middleware turned into a response. The library
defines the contract and depends on no vendor SDK, so each reporter adapts one provider and the middleware accepts any
number of them.

Each reporter receives a `ReportedError` carrying the status the middleware already resolved and the correlation
identifier the request carries, so no adapter reclassifies the error or learns where that identifier is stored.

Reporting is the best effort and isolated per reporter. Reporters run synchronously, after the response has been
produced and the log entry emitted, and whatever a reporter throws is discarded, so a provider that fails never fails
the response. A slow provider does add its own duration to the response, so one reached over the network should buffer
and flush on shutdown. When no provider is registered the middleware holds a `SilentErrorReporter`, the role PSR-3 gives
to its `NullLogger`, so registering it explicitly switches reporting off without changing the shape of the wiring.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\ReportedError;

# The mapping and the provider client are supplied by the consumer.
$mapping = /** @var ExceptionMapping */ null;
$client = /** @var ErrorTrackingClient */ null;

# One reporter adapts one provider. This one forwards only what no rule described.
final readonly class ErrorTrackingReporter implements ErrorReporter
{
    public function __construct(private ErrorTrackingClient $client)
    {
    }

    public function report(ReportedError $error): void
    {
        if ($error->wasMapped()) {
            return;
        }

        $this->client->capture(
            exception: $error->exception,
            correlationId: $error->correlationId?->toString()
        );
    }
}

# Register as many reporters as there are providers to notify, one withReporter call per provider.
$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->withReporter(reporter: new ErrorTrackingReporter(client: $client))
    ->build();
```

Whether an error is worth forwarding is the same decision in every reporter, so it is a type instead of a copy.
`ReportingFilter` answers it from the resolved error, and an adapter takes one as a constructor argument.

| Filter                       | Forwards                                                                         |
|------------------------------|----------------------------------------------------------------------------------|
| `SERVER_ERRORS`              | Only what the middleware answered with a 5xx, the default of the shipped adapter |
| `SERVER_ERRORS_AND_UNMAPPED` | Every 5xx, plus every exception no rule described, whatever its status           |
| `EVERY_ERROR`                | Everything, leaving the decision to whatever the provider offers                 |

### Supplementing an error from where it was raised

The middleware knows the request and the resolved status. What made a failure specific, the service that refused, the
tenant it belonged to, is known only where the exception was raised. An exception that implements `ReportingContext`
carries it, and every reporter reads it the same way.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ReportingContext;

final class UpstreamUnavailable extends RuntimeException implements ReportingContext
{
    public function __construct(string $message, private readonly string $service)
    {
        parent::__construct(message: $message);
    }

    public function reportingTags(): array
    {
        return ['service' => $this->service];
    }
}
```

Every entry of `reportingTags` becomes an index at the provider, so a key stays stable across releases and a value stays
drawn from a set the reader can reason about.

The rule that derives those three from the error itself is the default, and a consumer replaces it whole through
`withPriority`, which receives the rule that matched and the resolved status. `LOW` is never derived, because whether a
failure is known noise is a judgment only the application owner holds.

An exception that disagrees about how much attention it deserves implements `PrioritizedError` instead, and one that
does both implements both. The two are separate because they answer separate questions, and an exception with nothing to
say about urgency writes nothing rather than a method returning `null`.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\PrioritizedError;
use TinyBlocks\Http\ErrorHandler\ReportingPriority;

final class PaymentTimedOut extends RuntimeException implements PrioritizedError
{
    public function reportingPriority(): ReportingPriority
    {
        return ReportingPriority::CRITICAL;
    }
}
```

Providers spell urgency differently, one as a log level and another as a numeric score, so the scale is stated once and
each adapter translates it.

| Priority   | Means                                                                  |
|------------|------------------------------------------------------------------------|
| `CRITICAL` | A server error no rule described, a condition nobody wrote down        |
| `HIGH`     | A server error a rule described, a failure the application anticipated |
| `MEDIUM`   | A client error, the described outcome of a bad request                 |
| `LOW`      | Recorded for the trail, never a reason to interrupt anyone             |

### Tagging an error from the request

The middleware sits above the application, so the request it holds when an exception surfaces is the one from before
anything downstream learned anything. What a later layer resolves, the tenant behind a handle, the account a token
belongs to, never reaches it, because a PSR-7 request is immutable and every addition below produces a copy the
middleware never sees.

`ReportingTags` is the carrier that crosses that gap. The middleware leaves one on the request, any layer that learns
something writes into it, and every reporter reads what accumulated.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\Http\ErrorHandler\ReportingTags;

final readonly class RequireMembership implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $organizationId = $request->getHeaderLine('Organization-Id');

        ReportingTags::from(request: $request)?->add(key: 'organization_id', value: $organizationId);

        return $handler->handle($request);
    }
}
```

The carrier is absent when no error middleware sits above the caller, which is what the null-safe call answers, so the
line is a no-op in a stack that does not report rather than a failure.

Use it for what describes the request as a whole. What describes one failure belongs on the exception, through
`ReportingContext`, and wins over an entry of the same name here.

### Reporting errors to Sentry

`SentryReporter` is the adapter the library ships, and there are two ways in.

The first takes the three values every error tracking provider is configured with, boots the SDK, and binds the
resulting hub as the current one, so whatever the SDK captures outside this reporter, a fatal error most of all, reaches
the same project. An application taking this path writes no SDK code at all.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\ExceptionMapping;
use TinyBlocks\Http\ErrorHandler\Reporters\SentryReporter;

# The mapping and the deployment values are supplied by the consumer.
$mapping = /** @var ExceptionMapping */ null;
$dsn = /** @var string */ '';
$release = /** @var string */ '';
$environment = /** @var string */ '';

$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->withReporter(
        reporter: SentryReporter::initializedWith(
            dsn: $dsn,
            release: $release,
            environment: $environment
        )
    )
    ->build();
```

An empty release becomes no release, because a working tree is not a version. An empty DSN leaves the SDK disabled,
which is what an environment with reporting switched off wants, so the same wiring serves every environment.

The second takes a hub the application already holds, for an application that boots the SDK itself because it needs a
sample rate, a transport, or an integration this reporter does not configure. It touches no global state.

```php
$middleware = ErrorMiddleware::create()
    ->withMapping(mapping: $mapping)
    ->withReporter(reporter: SentryReporter::from(hub: SentrySdk::getCurrentHub()))
    ->build();
```

The SDK is not a dependency of this library, so installing `tiny-blocks/http-error-handler` never pulls it in. An
application that registers this reporter declares it.

```bash
composer require sentry/sentry
```

A tag is indexed, so a field earns one when you filter, group, or route on it. The request path is drill-down rather
than an axis, because the transaction already carries the route that matched, so it travels in a context instead.

| Field            | Kind     | Value                                                                 |
|------------------|----------|-----------------------------------------------------------------------|
| `correlation_id` | tag      | Identifier the request carries, absent when the request carries none  |
| `error_code`     | tag      | Code of the rule that matched, absent when no rule matched            |
| `error_mapped`   | tag      | `true` when a rule matched, `false` otherwise                         |
| `status_code`    | tag      | Status the middleware resolved for the response                       |
| `http`           | context  | Request method and path, the query string discarded                   |
| `level`          | severity | The priority translated to the provider scale, from `info` to `fatal` |

Whatever the request accumulated through `ReportingTags` and whatever the exception declared through
`ReportingContext` become tags alongside those, and an entry the exception declares wins over one of the same name from
the request.

The filter defaults to `SERVER_ERRORS`, so a client error never reaches the project. The drop happens before the event
is built, which is earlier and cheaper than the SDK `beforeSend` hook. Pass another filter to widen what is reported.

```php
SentryReporter::from(hub: SentrySdk::getCurrentHub(), filter: ReportingFilter::EVERY_ERROR);
```

### Naming the route a request matched

A reported error carries the request path, which holds identifiers and names one request rather than one endpoint. The
route that matched is the low cardinality answer, and it is the only request dimension safe to index. Register a
`RouteMiddleware` and `ReportedError` carries it, which a `SentryReporter` maps to the Sentry transaction.

The ordering is the whole trick. An `ErrorMiddleware` sits above the router, because that is the only place a routing
exception can be caught, and by then the request it holds is the one from before the match. The `RouteMiddleware` sits
below the router, where the match is known, and writes the name into the carrier the error middleware left behind.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use TinyBlocks\Http\ErrorHandler\ErrorMiddleware;
use TinyBlocks\Http\ErrorHandler\RouteMiddleware;

# The middleware and the application are supplied by the consumer.
$middleware = /** @var ErrorMiddleware */ null;
$application = /** @var \Slim\App */ null;

# Registered first, so it runs closest to the route.
$application->add(RouteMiddleware::resolvedBy(
    resolver: static fn(ServerRequestInterface $request): ?string
        => RouteContext::fromRequest($request)->getRoute()?->getPattern()
));

# The router above it, and the error middleware above the router, which is what keeps a 404 reaching it.
$application->addRoutingMiddleware();
$application->add($middleware);
```

The resolver belongs to the consumer because only the consumer knows the framework it routes with. The example reads
Slim. Any framework works, as long as the closure returns the pattern and never the path.

### What the library throws

Three exceptions reach the consumer, and every one of them reports a configuration mistake rather than a runtime
condition.

| Exception              | Thrown when                                                             |
|------------------------|-------------------------------------------------------------------------|
| `MappingNotConfigured` | `build()` runs before a single mapping is registered                    |
| `HttpStatusOutOfRange` | a rule maps to a status that is not a known HTTP error status           |
| `HttpHeaderMalformed`  | a rule declares a header whose name or value is not a well-formed field |

A rule closed with `mapsTo` builds its `MappedError` while the table is being declared, so a wrong status or a malformed
header fails at boot rather than on the first request that would have returned it. A rule closed with
`resolvesWith` builds one when the exception is caught, so the same two checks run then.

## License

Http Error Handler is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
