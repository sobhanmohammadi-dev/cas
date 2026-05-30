<?php
namespace CAS\Nodes;

class PlusNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '+'; }
}
