<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Solving;

use Sobhanmohammadi\CAS\Explain\Step;

/** A Solution plus the narrated Steps that were taken to reach it. */
final class SolvedEquation
{
    /** @param Step[] $steps */
    public function __construct(
        public readonly Solution $solution,
        public readonly array $steps,
    ) {
    }
}
