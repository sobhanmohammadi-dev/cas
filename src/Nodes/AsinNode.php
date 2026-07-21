<?php
namespace Sobhanmohammadi\CAS\Nodes;

/** asin(x) — inverse sine, x in [-1, 1], result in radians. */
class AsinNode extends TrigFunctionNode
{
    public function getFunctionName(): string { return 'asin'; }
}
