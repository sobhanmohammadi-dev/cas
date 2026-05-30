<?php
namespace CAS\Nodes;

class VariableNode extends MathNode
{
    private string $name;

    public function __construct(string $name, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->name = $name;
    }

    public function getName(): string      { return $this->name; }
    public function toMathString(): string { return $this->name; }
    public function __toString(): string   { return $this->name; }
}
