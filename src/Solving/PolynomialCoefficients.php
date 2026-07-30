<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Solving;

use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;
use Sobhanmohammadi\CAS\Number\Rational;

/**
 * Reduces `expression = 0` (single variable) into a map of
 * degree => coefficient, e.g. 3x^2 - 2x + 1 becomes [0 => 1, 1 => -2, 2 => 3].
 *
 * Replaces the old LinearSolverTrait/QuadraticSolverTrait duplication with
 * a single general extractor that both the linear and quadratic solvers
 * build on.
 */
final class PolynomialCoefficients
{
    private readonly ExactEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new ExactEvaluator();
    }

    /**
     * @return array<int, Rational> degree => coefficient (only nonzero degrees present,
     *                               plus always includes degree 0 for a constant term of 0)
     */
    public function extract(Node $node, string $variable): array
    {
        $terms = $this->collectTerms($node, $variable);
        $coefficients = [];
        foreach ($terms as [$degree, $coefficient]) {
            $coefficients[$degree] = ($coefficients[$degree] ?? Rational::fromInt(0))->add($coefficient);
        }
        ksort($coefficients);
        return $coefficients;
    }

    public function containsVariable(Node $node, string $variable): bool
    {
        if ($node instanceof VariableNode) {
            return $node->name === $variable;
        }
        if ($node instanceof NumberNode) {
            return false;
        }
        foreach ($node->children() as $child) {
            if ($this->containsVariable($child, $variable)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, array{0:int,1:Rational}> list of [degree, coefficient] pairs */
    private function collectTerms(Node $node, string $variable): array
    {
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Add) {
            return [...$this->collectTerms($node->left, $variable), ...$this->collectTerms($node->right, $variable)];
        }

        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Subtract) {
            return [...$this->collectTerms($node->left, $variable), ...$this->negateTerms($this->collectTerms($node->right, $variable))];
        }

        if ($node instanceof NegateNode) {
            return $this->negateTerms($this->collectTerms($node->operand, $variable));
        }

        [$degree, $coefficient] = $this->analyzeTerm($node, $variable);
        return [[$degree, $coefficient]];
    }

    /** @param array<int, array{0:int,1:Rational}> $terms @return array<int, array{0:int,1:Rational}> */
    private function negateTerms(array $terms): array
    {
        return array_map(fn (array $t) => [$t[0], $t[1]->negate()], $terms);
    }

    /** @return array{0:int,1:Rational} */
    private function analyzeTerm(Node $node, string $variable): array
    {
        if (!$this->containsVariable($node, $variable)) {
            return [0, $this->evaluator->evaluate($node)];
        }

        if ($node instanceof VariableNode) {
            return [1, Rational::fromInt(1)];
        }

        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Power) {
            if ($node->left instanceof VariableNode
                && $node->left->name === $variable
                && $node->right instanceof NumberNode
                && $node->right->value->isInteger()
            ) {
                return [$node->right->value->toInt(), Rational::fromInt(1)];
            }
            throw new UnsupportedOperationException("Unsupported polynomial term: {$node}");
        }

        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Multiply) {
            [$leftDegree, $leftCoeff] = $this->termFactor($node->left, $variable);
            [$rightDegree, $rightCoeff] = $this->termFactor($node->right, $variable);
            return [$leftDegree + $rightDegree, $leftCoeff->mul($rightCoeff)];
        }

        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Divide
            && !$this->containsVariable($node->right, $variable)
        ) {
            [$degree, $coeff] = $this->analyzeTerm($node->left, $variable);
            return [$degree, $coeff->div($this->evaluator->evaluate($node->right))];
        }

        throw new UnsupportedOperationException("Unsupported polynomial term: {$node}");
    }

    /** @return array{0:int,1:Rational} */
    private function termFactor(Node $node, string $variable): array
    {
        if (!$this->containsVariable($node, $variable)) {
            return [0, $this->evaluator->evaluate($node)];
        }
        return $this->analyzeTerm($node, $variable);
    }
}
