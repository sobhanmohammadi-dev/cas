<?php
namespace Sobhanmohammadi\CAS\Nodes;

class UnaryNode extends MathNode
{
    private string $op;
    private MathNode $operand;

    public function __construct(string $op, MathNode $operand, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->op      = $op;
        $this->operand = $operand;
    }

    public function getOp(): string       { return $this->op; }
    public function getOperand(): MathNode { return $this->operand; }

    public function __toString(): string
    {
        return $this->op . '(' . $this->operand . ')';
    }
}
