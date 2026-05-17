<?php
namespace CAS\Nodes;

abstract class BinaryOperatorNode extends MathNode {
    protected MathNode $left;
    protected MathNode $right;

    public function __construct(MathNode $left, MathNode $right, int $s, int $e) {
        parent::__construct($s, $e);
        $this->left = $left;
        $this->right = $right;
    }

    abstract public function getOperatorSymbol(): string;

    public function getLeft(): MathNode {
        return $this->left;
    }

    public function getRight(): MathNode {
        return $this->right;
    }
    public function __toString(): string {
        return '(' . $this->left->__toString() . ' ' . $this->getOperatorSymbol() . ' ' . $this->right->__toString() . ')';
    }
}