<?php
namespace CAS\Services;

use CAS\Nodes\MathNode;

class SymbolicEvaluator
{
    private SymbolTable $symbolTable;
    private Simplifier $simplifier;


    public function __construct(?SymbolTable $symbolTable = null)
    {
        $this->symbolTable = $symbolTable ?? new SymbolTable();
        $this->simplifier = new Simplifier($this->symbolTable);
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
}