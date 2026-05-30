<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode,
    MinusNode, DivideNode, UnaryNode,
    EquationNode
};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};

class SymbolicSolver
{
    use LinearSolverTrait;

    private SymbolTable $symbolTable;
    private Simplifier  $simplifier;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->simplifier  = new Simplifier($symbolTable);
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
        // Temporarily remove the unknown from the symbol table so it is
        // treated as a free variable during simplification.
        $savedValue = $this->symbolTable->lookup($unknown);
        if ($savedValue !== null) {
            $this->symbolTable->remove($unknown);
        }

        try {
            // LHS − RHS = 0
            $diff = $this->simplifier->simplifyFully(
                new MinusNode($eqNode->getLeft(), $eqNode->getRight(), 0, 0)
            );

            [$coeff, $constant] = $this->extractLinearCoefficient($diff, $unknown);

            $coeff    = $this->simplifier->simplifyFully($coeff);
            $constant = $this->simplifier->simplifyFully($constant);

            if ($this->isZeroNode($coeff)) {
                if ($this->isZeroNode($constant)) {
                    throw new \RuntimeException('Infinite solutions (identity).');
                }
                throw new \RuntimeException('No solution (contradiction).');
            }

            // solution = -constant / coeff
            $solution = $this->simplifier->simplifyFully(
                new DivideNode(new UnaryNode('-', $constant, 0, 0), $coeff, 0, 0)
            );

            return $solution;
        } finally {
            if ($savedValue !== null) {
                $this->symbolTable->assign($unknown, $savedValue);
            }
        }
    }

    private function isZeroNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_cmp($node->getValue(), 0) === 0;
    }
}
