<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

use Sobhanmohammadi\CAS\Node\Node;

/** The final simplified expression plus the ordered list of steps taken to reach it. */
final class SimplificationTrace
{
    /** @param Step[] $steps */
    public function __construct(
        public readonly Node $result,
        public readonly array $steps,
    ) {
    }
}
