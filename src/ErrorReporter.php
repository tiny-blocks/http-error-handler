<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Consumer-provided sink that forwards a handled error to an external observability provider.
 *
 * <p>One implementation adapts one provider, and the middleware accepts any number of them, so a
 * single application can report to an error tracking service, a metrics backend, and an audit trail
 * without any of them knowing about the others.</p>
 *
 * <p>Reporting is the best effort. Reporters run synchronously, after the response has been produced
 * and after the log entry has been emitted, and any failure thrown by an implementation is
 * discarded, so a provider that fails never fails the response. A slow one still adds its own
 * duration to it.</p>
 */
interface ErrorReporter
{
    /**
     * Reports the error to the provider this reporter adapts.
     *
     * <p>Implementations are free to throw. The middleware discards whatever comes out, so an
     * implementation never needs to defend itself for the sake of the request.</p>
     *
     * @param ReportedError $error The resolved error context to forward.
     */
    public function report(ReportedError $error): void;
}
