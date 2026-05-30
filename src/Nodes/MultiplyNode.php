<?php
namespace CAS\Nodes;

class MultiplyNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '*'; }
}
