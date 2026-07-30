<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Solving;

use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Evaluation\NumericEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Exception\CasException;
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Explain\Step;
use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\FunctionKind;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Number\Rational;

/**
 * Solves equations of the form `B*sqrt(g(x)) + K = 0` (equivalently, any
 * equation with exactly one square-root term and everything else constant)
 * by isolating the radical, squaring both sides, solving the resulting
 * polynomial with EquationSolver, and verifying every candidate root by
 * substitution back into the original equation -- squaring can introduce
 * extraneous roots, so verification is not optional.
 */
final class RadicalEquationSolver
{
    private readonly PolynomialCoefficients $coefficients;
    private readonly EquationSolver $polynomialSolver;
    private readonly ExactEvaluator $exactEvaluator;
    private readonly NumericEvaluator $numericEvaluator;

    public function __construct()
    {
        $this->coefficients = new PolynomialCoefficients();
        $this->polynomialSolver = new EquationSolver();
        $this->exactEvaluator = new ExactEvaluator();
        $this->numericEvaluator = new NumericEvaluator();
    }

    public function canHandle(EquationNode $equation, string $variable): bool
    {
        try {
            $this->extractRadicalTerm($equation, $variable);
            return true;
        } catch (CasException) {
            return false;
        }
    }

    public function solve(EquationNode $equation, string $variable): Solution
    {
        return $this->solveWithSteps($equation, $variable)->solution;
    }

    public function solveWithSteps(EquationNode $equation, string $variable): SolvedEquation
    {
        [$sqrtCoefficient, $inner, $constant] = $this->extractRadicalTerm($equation, $variable);
        $steps = [];

        // B*sqrt(g(x)) + K = 0  =>  sqrt(g(x)) = -K/B
        $isolatedValue = $constant->negate()->div($sqrtCoefficient);
        $isolateRule = Translatable::of('rule.isolate_radical', 'Isolate the Square Root');
        $steps[] = new Step(
            title: $isolateRule,
            currentExpression: (string) $equation,
            rule: $isolateRule,
            result: 'sqrt(' . $inner . ') = ' . $isolatedValue->toMathString(),
            updatedExpression: 'sqrt(' . $inner . ') = ' . $isolatedValue->toMathString(),
            formula: Translatable::of('formula.isolate_radical', 'a + b*sqrt(g(x)) = c  =>  sqrt(g(x)) = (c - a) / b'),
        );

        if ($isolatedValue->isNegative()) {
            $noSolutionRule = Translatable::of('rule.negative_radical', 'A square root cannot equal a negative number');
            $steps[] = new Step(
                title: $noSolutionRule,
                currentExpression: 'sqrt(' . $inner . ') = ' . $isolatedValue->toMathString(),
                rule: $noSolutionRule,
                result: 'no real solutions',
                updatedExpression: 'no real solutions',
            );
            return new SolvedEquation(Solution::empty(hasNoRealSolution: true), $steps);
        }

        // Square both sides.
        $squaredValue = $isolatedValue->mul($isolatedValue);
        $squareRule = Translatable::of('rule.square_both_sides', 'Square Both Sides');
        $polynomialEquation = new EquationNode($inner, new NumberNode($squaredValue));
        $steps[] = new Step(
            title: $squareRule,
            currentExpression: 'sqrt(' . $inner . ') = ' . $isolatedValue->toMathString(),
            rule: $squareRule,
            result: (string) $polynomialEquation,
            updatedExpression: (string) $polynomialEquation,
            formula: Translatable::of('formula.square_both_sides', '(sqrt(a))^2 = a'),
        );

        $solvedPolynomial = $this->polynomialSolver->solveWithSteps($polynomialEquation, $variable);
        foreach ($solvedPolynomial->steps as $step) {
            $steps[] = $step;
        }

        // Verify every candidate root against the original equation.
        $verifiedRoots = [];
        foreach ($solvedPolynomial->solution->roots as $root) {
            $isValid = $this->verify($equation, $variable, $root);
            $verifyRule = Translatable::of('rule.verify_solution', 'Verify by Substitution');
            $symbols = (new SymbolTable())->with($variable, $root);
            $steps[] = new Step(
                title: $verifyRule,
                currentExpression: (string) $equation,
                rule: $verifyRule,
                result: $isValid
                    ? "{$variable} = {$root->toMathString()} satisfies the original equation"
                    : "{$variable} = {$root->toMathString()} is extraneous (introduced by squaring)",
                updatedExpression: $variable . ' = ' . $root->toMathString(),
                formula: Translatable::of('formula.verify_substitution', 'Substitute {variable} = {root} into the original equation', [
                    'variable' => $variable,
                    'root' => $root->toMathString(),
                ]),
            );
            if ($isValid) {
                $verifiedRoots[] = $root;
            }
        }

        if ($verifiedRoots === []) {
            return new SolvedEquation(Solution::empty(hasNoRealSolution: true), $steps);
        }

        return new SolvedEquation(new Solution($verifiedRoots), $steps);
    }

    private function verify(EquationNode $equation, string $variable, Rational $root): bool
    {
        $symbols = (new SymbolTable())->with($variable, $root);
        try {
            $left = $this->numericEvaluator->evaluate($equation->left, $symbols);
            $right = $this->numericEvaluator->evaluate($equation->right, $symbols);
        } catch (CasException) {
            return false;
        }
        return abs($left - $right) < 1e-9;
    }

    /**
     * @return array{0:Rational,1:Node,2:Rational} [sqrt coefficient, radicand expression, constant remainder]
     * @throws UnsupportedOperationException if the equation isn't of the supported shape
     */
    private function extractRadicalTerm(EquationNode $equation, string $variable): array
    {
        $difference = new BinaryNode(BinaryOperator::Subtract, $equation->left, $equation->right);
        $terms = $this->flatten($difference);

        $sqrtTerm = null;
        $constant = Rational::fromInt(0);

        foreach ($terms as [$base, $coefficient]) {
            if ($base instanceof FunctionNode && $base->kind === FunctionKind::Sqrt) {
                if ($sqrtTerm !== null) {
                    throw new UnsupportedOperationException('Only a single square-root term is supported.');
                }
                $sqrtTerm = [$coefficient, $base->arguments[0]];
                continue;
            }
            if ($base === null) {
                $constant = $constant->add($coefficient);
                continue;
            }
            if ($this->coefficients->containsVariable($base, $variable)) {
                throw new UnsupportedOperationException('Radical equation has variable terms outside the square root.');
            }
            $constant = $constant->add($this->exactEvaluator->evaluate($base)->mul($coefficient));
        }

        if ($sqrtTerm === null) {
            throw new UnsupportedOperationException('No square-root term found.');
        }

        return [$sqrtTerm[0], $sqrtTerm[1], $constant];
    }

    /** @return array<int, array{0:Node|null,1:Rational}> */
    private function flatten(Node $node): array
    {
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Add) {
            return [...$this->flatten($node->left), ...$this->flatten($node->right)];
        }
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Subtract) {
            return [...$this->flatten($node->left), ...$this->negateAll($this->flatten($node->right))];
        }
        if ($node instanceof NegateNode) {
            return $this->negateAll($this->flatten($node->operand));
        }
        if ($node instanceof NumberNode) {
            return [[null, $node->value]];
        }
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Multiply) {
            if ($node->left instanceof NumberNode) {
                return [[$node->right, $node->left->value]];
            }
            if ($node->right instanceof NumberNode) {
                return [[$node->left, $node->right->value]];
            }
        }
        return [[$node, Rational::fromInt(1)]];
    }

    /** @param array<int, array{0:Node|null,1:Rational}> $terms @return array<int, array{0:Node|null,1:Rational}> */
    private function negateAll(array $terms): array
    {
        return array_map(fn (array $t) => [$t[0], $t[1]->negate()], $terms);
    }
}
