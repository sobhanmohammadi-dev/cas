<?php
namespace Sobhanmohammadi\CAS\Nodes;

class EquationNode extends MathNode
{
    private MathNode $leftExpr;
    private MathNode $rightExpr;

    public function __construct(MathNode $left, MathNode $right, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->leftExpr  = $left;
        $this->rightExpr = $right;
    }

    public function getLeft(): MathNode  { return $this->leftExpr; }
    public function getRight(): MathNode { return $this->rightExpr; }

    public function __toString(): string
    {
        return $this->leftExpr . ' = ' . $this->rightExpr;
    }
}
