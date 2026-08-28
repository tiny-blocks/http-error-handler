<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use TinyBlocks\Http\Code;

/**
 * Attention an error deserves once it reaches the people who read the result.
 *
 * <p>Providers express urgency in their own vocabulary, one as a log level, another as a numeric
 * score, so the scale is stated once in neutral terms and each adapter translates it. What a caller
 * declares here survives a change of provider. These four are where the scales that route human
 * attention converge, and they are the words two of them already use.</p>
 *
 * <p>The resolved status carries the default, because a status is what the middleware already knows
 * about every error. A caller that disagrees says so through {@see PrioritizedError}.</p>
 */
enum ReportingPriority
{
    case LOW;
    case HIGH;
    case MEDIUM;
    case CRITICAL;

    /**
     * Derives the priority the resolved error justifies on its own.
     *
     * <p>A client error is the described outcome of a bad request, so it sits in the middle. A server
     * error that a rule described is a failure the application anticipated. One that no rule described
     * is a condition nobody wrote down, which is the strongest thing this library can say without
     * guessing on behalf of the consumer.</p>
     *
     * @param MappedError|null $mapped The rule that matched, or <code>null</code> when none did.
     * @param Code $status The status the middleware resolved for the response.
     * @return ReportingPriority Medium for a client error, critical for an undescribed server error, high otherwise.
     */
    public static function from(?MappedError $mapped, Code $status): ReportingPriority
    {
        if ($status->isClientError()) {
            return ReportingPriority::MEDIUM;
        }

        return is_null($mapped) ? ReportingPriority::CRITICAL : ReportingPriority::HIGH;
    }
}
