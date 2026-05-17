<?php
namespace CAS\Services;

use CAS\Nodes\{
    MathNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, VariableNode, EquationNode
};
use CAS\Parser\{
    Lexer, Parser
};

class SymbolicSolver
{
    private SymbolTable $symbolTable;
    private Simplifier $simplifier;
    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->simplifier = new Simplifier($symbolTable);
    }

    public function solve(string $equation, string $unknown): MathNode
    {
        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        $eqNode = $parser->parseEquation();

        return $this->solveEquationNode($eqNode, $unknown);
    }

    public function solveEquationNode(EquationNode $eqNode, string $unknown): MathNode
    {
        $origValue = $this->symbolTable->lookup($unknown);
        if ($origValue !== null) {
            $this->symbolTable->remove($unknown);
        }

        try {
            $diff = new MinusNode(
                $eqNode->getLeft(),
                $eqNode->getRight(),
                $eqNode->getStartPos(),
                $eqNode->getEndPos()
            );

            $diff = $this->simplifier->simplifyFully($diff);

            [$coeff, $constant] = $this->extractLinearCoefficient($diff, $unknown);

            $coeff    = $this->simplifier->simplifyFully($coeff);
            $constant = $this->simplifier->simplifyFully($constant);

            if ($this->isZeroNode($coeff)) {
                if ($this->isZeroNode($constant)) {
                    throw new \RuntimeException('Infinite solutions (identity).');
                } else {
                    throw new \RuntimeException('No solution (contradiction).');
                }
            }

            $negConst = new UnaryNode('-', $constant, 0, 0);
            $solution = new DivideNode($negConst, $coeff, 0, 0);

            return $this->simplifier->simplifyFully($solution);
        } finally {
            if ($origValue !== null) {
                $this->symbolTable->assign($unknown, $origValue);
            }
        }
    }

    private function extractLinearCoefficient(MathNode $expr, string $unknown): array
    {
        if ($expr instanceof VariableNode && $expr->getName() === $unknown) {
            return [
                new IntegerNode('1', $expr->getStartPos(), $expr->getEndPos()),
                new IntegerNode('0', $expr->getStartPos(), $expr->getEndPos())
            ];
        }

        if ($expr instanceof PiNode) {
            return [
                new IntegerNode('0', $expr->getStartPos(), $expr->getEndPos()),
                $expr
            ];
        }

        if ($expr instanceof UnaryNode) {
            $op = $expr->getOp();
            if ($op !== '-') {
                throw new \RuntimeException('Unsupported unary operator in linear equation.');
            }
            $inner = $expr->getOperand();
            [$c, $k] = $this->extractLinearCoefficient($inner, $unknown);
            // - (c*u + k) = (-c)*u + (-k)
            return [
                new UnaryNode('-', $c, 0, 0),
                new UnaryNode('-', $k, 0, 0)
            ];
        }

        if ($expr instanceof PlusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new PlusNode($c1, $c2, 0, 0),
                new PlusNode($k1, $k2, 0, 0)
            ];
        }

        if ($expr instanceof MinusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new MinusNode($c1, $c2, 0, 0),
                new MinusNode($k1, $k2, 0, 0)
            ];
        }

        if ($expr instanceof MultiplyNode) {
            return $this->extractFromProduct($expr, $unknown);
        }

        if ($expr instanceof DivideNode) {
            $num = $expr->getLeft();
            $den = $expr->getRight();
            if ($this->containsVariable($den, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in denominator.');
            }
            [$c, $k] = $this->extractLinearCoefficient($num, $unknown);
            return [
                new DivideNode($c, $den, 0, 0),
                new DivideNode($k, $den, 0, 0)
            ];
        }

        if ($expr instanceof PowerNode) {
            $base = $expr->getLeft();
            $exp  = $expr->getRight();
            if ($this->containsVariable($exp, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in exponent.');
            }
            if ($this->containsVariable($base, $unknown)) {
                if ($exp instanceof IntegerNode && \gmp_cmp($exp->getValue(), 1) === 0) {
                    return $this->extractLinearCoefficient($base, $unknown);
                }
                throw new \RuntimeException('Nonlinear equation: variable raised to power > 1.');
            }
            return [
                new IntegerNode('0', 0, 0),
                $expr
            ];
        }

        // Sqrt و Root
        if ($expr instanceof \CAS\Nodes\SqrtNode || $expr instanceof \CAS\Nodes\RootNode) {
            if ($this->containsVariable($expr, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable inside radical.');
            }
            return [
                new IntegerNode('0', 0, 0),
                $expr
            ];
        }

        if ($expr instanceof IntegerNode || $expr instanceof RationalNode || $expr instanceof \CAS\Nodes\ComplexNode) {
            return [
                new IntegerNode('0', 0, 0),
                $expr
            ];
        }

        if ($expr instanceof VariableNode) {
            return [
                new IntegerNode('0', 0, 0),
                $expr
            ];
        }

        throw new \RuntimeException('Unsupported node type in linear equation: ' . get_class($expr));
    }

    private function extractFromProduct(MultiplyNode $mul, string $unknown): array
    {
        $left  = $mul->getLeft();
        $right = $mul->getRight();

        $leftHas  = $this->containsVariable($left, $unknown);
        $rightHas = $this->containsVariable($right, $unknown);

        if ($leftHas && $rightHas) {
            throw new \RuntimeException('Nonlinear equation: variable multiplied by itself.');
        }

        if (!$leftHas && !$rightHas) {
            return [
                new IntegerNode('0', 0, 0),
                $mul
            ];
        }

        if ($leftHas) {
            $varFactor = $left;
            $constFactor = $right;
        } else {
            $varFactor = $right;
            $constFactor = $left;
        }

        [$c, $k] = $this->extractLinearCoefficient($varFactor, $unknown);

        // constFactor * (c * u + k) = (constFactor * c) * u + (constFactor * k)
        return [
            new MultiplyNode($constFactor, $c, 0, 0),
            new MultiplyNode($constFactor, $k, 0, 0)
        ];
    }


    private function containsVariable(MathNode $node, string $varName): bool
    {
        if ($node instanceof VariableNode) {
            return $node->getName() === $varName;
        }

        if ($node instanceof BinaryOperatorNode) {
            return $this->containsVariable($node->getLeft(), $varName)
                || $this->containsVariable($node->getRight(), $varName);
        }

        if ($node instanceof UnaryNode) {
            return $this->containsVariable($node->getOperand(), $varName);
        }

        if ($node instanceof \CAS\Nodes\SqrtNode) {
            return $this->containsVariable($node->getRadicand(), $varName);
        }

        if ($node instanceof \CAS\Nodes\RootNode) {
            return $this->containsVariable($node->getDegree(), $varName)
                || $this->containsVariable($node->getRadicand(), $varName);
        }

        if ($node instanceof \CAS\Nodes\PowerNode) {
            return $this->containsVariable($node->getLeft(), $varName)
                || $this->containsVariable($node->getRight(), $varName);
        }

        return false;
    }

    private function isZeroNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_cmp($node->getValue(), 0) === 0;
    }
}