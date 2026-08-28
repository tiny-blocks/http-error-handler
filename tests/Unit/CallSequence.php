<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Http\ErrorHandler\Unit;

final class CallSequence
{
    /** @var string[] */
    public array $recorded = [];

    public function record(string $name): void
    {
        $this->recorded[] = $name;
    }
}
