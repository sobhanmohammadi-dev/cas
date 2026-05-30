<?php
namespace CAS\Nodes;

class DivideNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '/'; }
}
