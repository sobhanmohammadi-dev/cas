<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode,
    VariableNode, PiNode, BinaryOperatorNode
};

/**
 * Shared linear-equation machinery used by SymbolicSolver, NumericSolver,
 * StepSolver, and SymbolicStepSolver.
 *
 * extractLinearCoefficient() decomposes an expression into:
 *   (coefficient, constant)  such that  expr = coefficient * unknown + constant
 *
 * Throws \RuntimeException with message starting "Nonlinear" when the
 * expression is not linear in the given unknown.
 */
trait LinearSolverTrait
{
    /**
     * @return array{0: MathNode, 1: MathNode}  [coefficient, constant]
     */
    private function extractLinearCoefficient(MathNode $expr, string $unknown): array
    {
        // x  →  [1, 0]
        if ($expr instanceof VariableNode && $expr->getName() === $unknown) {
            return [new IntegerNode('1', 0, 0), new IntegerNode('0', 0, 0)];
        }

        // numeric / π / other variable  →  [0, expr]
        if ($expr instanceof IntegerNode
            || $expr instanceof RationalNode
            || $expr instanceof PiNode
        ) {
            return [new IntegerNode('0', 0, 0), $expr];
        }

        if ($expr instanceof VariableNode) {
            // A different variable — treat as constant
            return [new IntegerNode('0', 0, 0), $expr];
        }

        // -(expr)  →  [-c, -k]
        if ($expr instanceof UnaryNode) {
            if ($expr->getOp() !== '-') {
                throw new \RuntimeException('Unsupported unary operator in linear equation.');
            }
            [$c, $k] = $this->extractLinearCoefficient($expr->getOperand(), $unknown);
            return [
                new UnaryNode('-', $c, 0, 0),
                new UnaryNode('-', $k, 0, 0),
            ];
        }

        // (a + b)  →  [ca+cb, ka+kb]
        if ($expr instanceof PlusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new PlusNode($c1, $c2, 0, 0),
                new PlusNode($k1, $k2, 0, 0),
            ];
        }

        // (a - b)  →  [ca-cb, ka-kb]
        if ($expr instanceof MinusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new MinusNode($c1, $c2, 0, 0),
                new MinusNode($k1, $k2, 0, 0),
            ];
        }

        // a * b
        if ($expr instanceof MultiplyNode) {
            return $this->extractFromProduct($expr, $unknown);
        }

        // a / b  — variable must not appear in denominator
        if ($expr instanceof DivideNode) {
            $num = $expr->getLeft();
            $den = $expr->getRight();
            if ($this->containsVariable($den, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in denominator.');
            }
            [$c, $k] = $this->extractLinearCoefficient($num, $unknown);
            return [
                new DivideNode($c, $den, 0, 0),
                new DivideNode($k, $den, 0, 0),
            ];
        }

        // a ^ b
        if ($expr instanceof PowerNode) {
            $base = $expr->getLeft();
            $exp  = $expr->getRight();
            if ($this->containsVariable($exp, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in exponent.');
            }
            if ($this->containsVariable($base, $unknown)) {
                // x^1 → linear
                if ($exp instanceof IntegerNode && \gmp_cmp($exp->getValue(), 1) === 0) {
                    return $this->extractLinearCoefficient($base, $unknown);
                }
                throw new \RuntimeException('Nonlinear equation: variable raised to power > 1.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }

        // Radicals containing the variable are non-linear
        if ($expr instanceof SqrtNode) {
            if ($this->containsVariable($expr->getRadicand(), $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable inside sqrt.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }

        if ($expr instanceof RootNode) {
            if ($this->containsVariable($expr->getDegree(),   $unknown)
                || $this->containsVariable($expr->getRadicand(), $unknown)
            ) {
                throw new \RuntimeException('Nonlinear equation: variable inside radical.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }

        throw new \RuntimeException('Unsupported node type in linear equation: ' . get_class($expr));
    }

    /**
     * Decompose a product: one factor contains the unknown, the other is constant.
     *
     * @return array{0: MathNode, 1: MathNode}
     */
    private function extractFromProduct(MultiplyNode $mul, string $unknown): array
    {
        $left  = $mul->getLeft();
        $right = $mul->getRight();
        $leftHas  = $this->containsVariable($left,  $unknown);
        $rightHas = $this->containsVariable($right, $unknown);

        if ($leftHas && $rightHas) {
            throw new \RuntimeException('Nonlinear equation: variable multiplied by itself.');
        }
        if (!$leftHas && !$rightHas) {
            return [new IntegerNode('0', 0, 0), $mul];
        }

        $varFactor   = $leftHas ? $left  : $right;
        $constFactor = $leftHas ? $right : $left;

        [$c, $k] = $this->extractLinearCoefficient($varFactor, $unknown);
        return [
            new MultiplyNode($constFactor, $c, 0, 0),
            new MultiplyNode($constFactor, $k, 0, 0),
        ];
    }

    /** Returns true if $node contains $varName anywhere in its tree. */
    private function containsVariable(MathNode $node, string $varName): bool
    {
        if ($node instanceof VariableNode) {
            return $node->getName() === $varName;
        }
        if ($node instanceof BinaryOperatorNode) {
            return $this->containsVariable($node->getLeft(),  $varName)
                || $this->containsVariable($node->getRight(), $varName);
        }
        if ($node instanceof UnaryNode) {
            return $this->containsVariable($node->getOperand(), $varName);
        }
        if ($node instanceof SqrtNode) {
            return $this->containsVariable($node->getRadicand(), $varName);
        }
        if ($node instanceof RootNode) {
            return $this->containsVariable($node->getDegree(),   $varName)
                || $this->containsVariable($node->getRadicand(), $varName);
        }
        return false;
    }
}


