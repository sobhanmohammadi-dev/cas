<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\MathNode;

class SymbolicEvaluator
{
    private SymbolTable $symbolTable;
    private Simplifier  $simplifier;

    public function __construct(?SymbolTable $symbolTable = null)
    {
        $this->symbolTable = $symbolTable ?? new SymbolTable();
        $this->simplifier  = new Simplifier($this->symbolTable);
    }

    public function evaluate(MathNode $expression): MathNode
    {
        return $this->simplifier->simplifyFully($expression);
    }

    public function assign(string $variableName, MathNode $value): void
    {
        $this->symbolTable->assign($variableName, $value);
    }

    public function getSymbolTable(): SymbolTable
    {
        return $this->symbolTable;
    }

    public function getSimplifier(): Simplifier
    {
        return $this->simplifier;
    }
}
