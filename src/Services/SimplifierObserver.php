<?php
namespace CAS\Services;

use CAS\Nodes\MathNode;

/**
 * Implement this interface and attach it to a Simplifier to receive
 * notifications whenever a simplification rule fires.
 */
interface SimplifierObserver
{
    /**
     * Called after a simplification rule transforms $before into $after.
     *
     * @param string   $ruleName  Human-readable rule label.
     * @param MathNode $before    Node before the transformation.
     * @param MathNode $after     Node after the transformation.
     */
    public function onRuleApplied(string $ruleName, MathNode $before, MathNode $after): void;
}
