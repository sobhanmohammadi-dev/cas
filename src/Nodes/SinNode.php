<?php
namespace Sobhanmohammadi\CAS\Nodes;

/** sin(x) — argument in radians, consistent with the rest of CAS's math conventions. */
class SinNode extends TrigFunctionNode
{
    public function getFunctionName(): string { return 'sin'; }
}
