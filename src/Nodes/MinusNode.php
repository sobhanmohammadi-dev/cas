<?php
namespace CAS\Nodes;

class MinusNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '-'; }
}
