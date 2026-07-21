<?php
namespace Sobhanmohammadi\CAS\Nodes;

/** tan(x) — argument in radians. */
class TanNode extends TrigFunctionNode
{
    public function getFunctionName(): string { return 'tan'; }
}
