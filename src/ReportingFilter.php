<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Rule that decides which handled errors a reporter forwards to its provider.
 *
 * <p>Reporting has a price, a metered event quota or the attention of whoever reads the result, so
 * what a reporter drops belongs to the design. The rule is stated once, in terms of the resolved
 * error, and every adapter reuses it rather than restating it in the vocabulary of its provider.</p>
 */
enum ReportingFilter: string
{
    case EVERY_ERROR = 'EVERY_ERROR';
    case SERVER_ERRORS = 'SERVER_ERRORS';
    case SERVER_ERRORS_AND_UNMAPPED = 'SERVER_ERRORS_AND_UNMAPPED';

    /**
     * Tells whether the error passes this filter.
     *
     * @param ReportedError $error The resolved error context the middleware produced.
     * @return bool True when the error is forwarded, false when it is dropped.
     */
    public function accepts(ReportedError $error): bool
    {
        return match ($this) {
            ReportingFilter::EVERY_ERROR                => true,
            ReportingFilter::SERVER_ERRORS              => $error->status->isServerError(),
            ReportingFilter::SERVER_ERRORS_AND_UNMAPPED => $error->status->isServerError() || !$error->wasMapped()
        };
    }
}
