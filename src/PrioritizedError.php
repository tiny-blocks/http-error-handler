<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Exception that names the attention it deserves, overriding the rule the middleware carries.
 *
 * <p>The middleware derives a priority from what it knows, the rule that matched and the status it
 * resolved. That is right for almost every failure and wrong for the few that read the same from
 * outside and mean something different, a timeout on a payment against a timeout on a thumbnail.</p>
 *
 * <p>An exception implements this only when it disagrees. Implementing it is the whole statement,
 * so the answer is a priority and never an absence: an exception with nothing to say about urgency
 * does not implement the interface. What it adds to the report instead belongs to
 * {@see ReportingContext}.</p>
 */
interface PrioritizedError
{
    /**
     * Names the attention this failure deserves.
     *
     * @return ReportingPriority The priority the middleware applies instead of the one it would derive.
     */
    public function reportingPriority(): ReportingPriority;
}
