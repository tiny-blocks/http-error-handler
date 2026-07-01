<?php

declare(strict_types=1);

namespace TinyBlocks\Http\ErrorHandler;

/**
 * Consumer-declared set of rules that translate the consumer's own exceptions into mapped errors.
 *
 * <p>A consumer implements this once per vertical and declares only the rules it owns. The
 * middleware composes the {@see ExceptionMappingTable} of every configured mapping into a single
 * first-match-wins lookup, so neither the consumer nor the middleware repeats the composition.</p>
 */
interface ExceptionMapping
{
    /**
     * Returns the table of rules that translate this mapping's exceptions into mapped errors.
     *
     * @return ExceptionMappingTable The rules this mapping contributes to the composed lookup.
     */
    public function mappings(): ExceptionMappingTable;
}
