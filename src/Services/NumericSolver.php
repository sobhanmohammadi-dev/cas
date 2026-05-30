<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\{MathNode, EquationNode};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};

/**
 * Solves a linear equation and returns the result as a fully-evaluated
 * numeric node (IntegerNode or RationalNode).
 *
 * Strategy: use SymbolicSolver to obtain the exact symbolic solution, then
 * run NumericEvaluator over it to reduce any remaining symbolic constants.
 * This replaces the previous fragile numerical-sampling approach.
 */
class NumericSolver
{
    private SymbolTable     $symbolTable;
    private SymbolicSolver  $symbolic;
    private NumericEvaluator $evaluator;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->symbolic    = new SymbolicSolver($symbolTable);
        $this->evaluator   = new NumericEvaluator($symbolTable);
    }

    public function solve(string $equation, string $unknown): MathNode
    {
        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        return $this->solveEquationNode($parser->parseEquation(), $unknown);
    }

    public function solveEquationNode(EquationNode $eqNode, string $unknown): MathNode
    {
        // Obtain the exact symbolic solution first
        $solution = $this->symbolic->solveEquationNode($eqNode, $unknown);

        // Evaluate the symbolic result to a pure number
        return $this->evaluator->evaluate($solution);
    }
}
