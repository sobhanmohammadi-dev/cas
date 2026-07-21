<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode,
    MinusNode, DivideNode, UnaryNode,
    EquationNode
};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;

class SymbolicSolver
{
    use LinearSolverTrait;
    use QuadraticSolverTrait;

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

    // ─── Quadratic equations ────────────────────────────────────────────

    /**
     * Solves ax^2 + bx + c = 0 for $unknown using the quadratic formula.
     * All other variables appearing in the equation must already be bound
     * in the SymbolTable (see QuadraticSolverTrait for why).
     *
     * @return QuadraticRoot[] Always 2 entries; both real and equal when
     *                         the discriminant is exactly zero.
     */
    public function solveQuadratic(string $equation, string $unknown): array
    {
        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        return $this->solveQuadraticEquationNode($parser->parseEquation(), $unknown);
    }

    /** @return QuadraticRoot[] */
    public function solveQuadraticEquationNode(EquationNode $eqNode, string $unknown): array
    {
        $diff = $this->simplifier->simplifyFully(
            new MinusNode($eqNode->getLeft(), $eqNode->getRight(), 0, 0)
        );

        [$a, $b, $c] = $this->extractQuadraticCoefficients($diff, $unknown);
        return $this->solveQuadraticFormula($a, $b, $c);
    }

    /**
     * Tries the linear solver first; if the equation turns out to be
     * nonlinear because of a squared/self-multiplied unknown, retries as
     * quadratic automatically. This is the "detect automatically" entry
     * point requested for general equation-solving workflows — callers
     * that already know the degree should call solve()/solveQuadratic()
     * directly instead.
     *
     * @return MathNode|QuadraticRoot[] A single MathNode for a linear
     *                                  equation, or two QuadraticRoot
     *                                  entries for a quadratic one.
     */
    public function solveAuto(string $equation, string $unknown)
    {
        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        $eqNode = $parser->parseEquation();

        try {
            return $this->solveEquationNode($eqNode, $unknown);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'nonlinear') === false) {
                throw $e;
            }
            return $this->solveQuadraticEquationNode($eqNode, $unknown);
        }
    }
}
