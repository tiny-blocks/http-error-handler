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
* [License](#license)
* [Contributing](#contributing)

## Overview

Provides a PSR-15 middleware that captures exceptions thrown by downstream handlers and translates them into structured
JSON error responses. Each consumer declares an `ExceptionMapping`, a class whose `mappings()` method returns an
`ExceptionMappingTable` that turns each known exception into a `MappedError` (machine-readable code, HTTP status between
400 and 599, human-readable message, optional headers). The consumer declares only the rules it owns, and the middleware
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

When several verticals each own a mapping (for example, a write side and a read side), pass them all to
`withMappings`. The middleware composes them into a single first-match-wins lookup, evaluating the mappings in the order
given, so the consumer never writes the composition by hand.

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

### Logging and displaying error details

Enables structured error logging and the optional inclusion of exception details in the response body. The defaults are
silent and secure: nothing is logged and no stack traces are returned to the client.

The log level follows the status of the response that is actually returned. A 4xx is logged as a warning and everything
else as an error, whether the status came from a mapping or from the fallback. A validation failure the consumer maps to
422 is a client mistake, and logging it as an error inflates every error-rate signal built on top of the logs.

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
    ->withFallbackOnUnmapped(false)
    ->build();
```

## License

Http Error Handler is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
