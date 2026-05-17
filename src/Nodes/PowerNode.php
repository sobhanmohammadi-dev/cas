<?php
namespace CAS\Nodes;

class PowerNode extends BinaryOperatorNode {
    public function __construct(MathNode $left, MathNode $right, int $s, int $e) {
        parent::__construct($left, $right, $s, $e);
    }
    public function getOperatorSymbol(): string {
        return '^';
    }
}