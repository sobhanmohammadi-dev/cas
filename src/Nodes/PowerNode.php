<?php
namespace Sobhanmohammadi\CAS\Nodes;

class PowerNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '^'; }
}
