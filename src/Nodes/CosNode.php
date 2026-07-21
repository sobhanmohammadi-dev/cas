<?php
namespace Sobhanmohammadi\CAS\Nodes;

/** cos(x) — argument in radians. */
class CosNode extends TrigFunctionNode
{
    public function getFunctionName(): string { return 'cos'; }
}
