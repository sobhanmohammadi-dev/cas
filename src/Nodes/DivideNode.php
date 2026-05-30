<?php
namespace Sobhanmohammadi\CAS\Nodes;

class DivideNode extends BinaryOperatorNode
{
    public function getOperatorSymbol(): string { return '/'; }
}
