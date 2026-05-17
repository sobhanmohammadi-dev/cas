<?php
namespace CAS\Nodes;

class VariableNode extends MathNode {
    private string $name;
    private ?MathNode $assignedValue;

    public function __construct(string $name, ?MathNode $assignedValue, int $s, int $e) {
        parent::__construct($s, $e);
        $this->name = $name;
        $this->assignedValue = $assignedValue;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getAssignedValue(): ?MathNode {
        return $this->assignedValue;
    }

    public function isBound(): bool {
        return $this->assignedValue !== null;
    }

    public function __toString(): string {
        if ($this->isBound()) {
            return $this->name;
        }
        return $this->name;
    }

    public function toMathString(): string
    {
        return $this->getName();
    }
}