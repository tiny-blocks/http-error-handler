<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Flags controlling logging behavior and the verbosity of error responses produced by
 * {@see ErrorMiddleware}.
 */
final readonly class ErrorHandlingSettings
{
    private function __construct(
        public bool $logErrors,
        public bool $logErrorDetails,
        public bool $displayErrorDetails
    ) {
    }

    /**
     * Creates an ErrorHandlingSettings from the given flags.
     *
     * @param bool $logErrors Whether to enable error logging when a logger is provided.
     * @param bool $logErrorDetails Whether to include exception class, file, line, and trace in the log context.
     * @param bool $displayErrorDetails Whether to include exception class, file, line, and trace in the response body.
     * @return ErrorHandlingSettings The configured settings instance.
     */
    public static function from(
        bool $logErrors,
        bool $logErrorDetails,
        bool $displayErrorDetails
    ): ErrorHandlingSettings {
        return new ErrorHandlingSettings(
            logErrors: $logErrors,
            logErrorDetails: $logErrorDetails,
            displayErrorDetails: $displayErrorDetails
        );
    }

    /**
     * Creates an ErrorHandlingSettings with all flags disabled.
     *
     * @return ErrorHandlingSettings The default settings instance.
     */
    public static function default(): ErrorHandlingSettings
    {
        return ErrorHandlingSettings::from(
            logErrors: false,
            logErrorDetails: false,
            displayErrorDetails: false
        );
    }
}
