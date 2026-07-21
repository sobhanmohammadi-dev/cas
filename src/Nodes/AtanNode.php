<?php
namespace Sobhanmohammadi\CAS\Nodes;

/** atan(x) — inverse tangent, result in radians in (-pi/2, pi/2). */
class AtanNode extends TrigFunctionNode
{
    public function getFunctionName(): string { return 'atan'; }
}
