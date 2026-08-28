<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler\Reporters;

use TinyBlocks\Http\ErrorHandler\ErrorReporter;
use TinyBlocks\Http\ErrorHandler\ReportedError;

/**
 * Reporter that accepts every report and forwards it to no provider.
 *
 * <p>It is the reporter the middleware holds when no provider was registered, the role PSR-3 gives
 * to its <code>NullLogger</code>. The middleware always holds a reporter, so no call site guards
 * against a missing one, and the absence of a provider is a type with a name instead of a
 * <code>null</code> carried through the wiring.</p>
 *
 * <p>Register it when reporting is resolved from configuration and switched off for an environment,
 * so the wiring keeps one shape whether a provider is configured or not.</p>
 */
final readonly class SilentErrorReporter implements ErrorReporter
{
    public function report(ReportedError $error): void
    {
    }
}
