<?php
namespace CAS\Nodes;

class AssignmentNode extends MathNode {
    private string $variableName;
    private MathNode $expression;

    public function __construct(string $variableName, MathNode $expression, int $s, int $e) {
        parent::__construct($s, $e);
        $this->variableName = $variableName;
        $this->expression = $expression;
    }

    public function getVariableName(): string {
        return $this->variableName;
    }

    public function getExpression(): MathNode {
        return $this->expression;
    }

    public function __toString(): string {
        return $this->variableName . ' = ' . $this->expression->__toString();
    }
}