<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification;

use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\Node;

/**
 * A single, focused rewrite rule. The Simplifier applies every registered
 * rule repeatedly (bottom-up) until none of them changes the tree any
 * further. This replaces the old SimplifierObserver duplication with a
 * small, independently testable strategy per rule.
 */
interface SimplificationRule
{
    /** Short human-readable, translatable label used in step-by-step explanations. */
    public function name(): Translatable;

    /** Return a rewritten node, or null if this rule does not apply. */
    public function apply(Node $node): ?Node;
}
