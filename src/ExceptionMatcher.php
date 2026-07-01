<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

use Throwable;

/**
 * Predicate that decides whether a given throwable is the target of a mapping rule.
 */
interface ExceptionMatcher
{
    /**
     * Tells whether the exception matches this rule's criteria.
     *
     * @param Throwable $exception The thrown exception to evaluate.
     * @return bool <code>true</code> when the exception matches, <code>false</code> otherwise.
     */
    public function matches(Throwable $exception): bool;
}
