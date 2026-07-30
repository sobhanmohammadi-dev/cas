<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Solving;

use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Explain\Step;
use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Number\Rational;
/**
 * Solves `lhs = rhs` for a single variable when the equation is constant,
 * linear, or quadratic in that variable. Higher-degree polynomials raise
 * UnsupportedOperationException.
 */
final class EquationSolver
{
    private readonly PolynomialCoefficients $coefficients;
    private ?RadicalEquationSolver $radicalSolver = null;

    public function __construct()
    {
        $this->coefficients = new PolynomialCoefficients();
    }

    public function solve(EquationNode $equation, string $variable): Solution
    {
        try {
            return $this->solvePolynomial($equation, $variable);
        } catch (UnsupportedOperationException $polynomialFailure) {
            return $this->radicalSolver()->canHandle($equation, $variable)
                ? $this->radicalSolver()->solve($equation, $variable)
                : throw $polynomialFailure;
        }
    }

    private function solvePolynomial(EquationNode $equation, string $variable): Solution
    {
        $difference = new BinaryNode(BinaryOperator::Subtract, $equation->left, $equation->right);
        $coefficients = $this->coefficients->extract($difference, $variable);

        $degree = $coefficients === [] ? 0 : max(array_keys($coefficients));
        $zero = Rational::fromInt(0);
        $get = fn (int $d): Rational => $coefficients[$d] ?? $zero;

        return match (true) {
            $degree === 0 => $get(0)->isZero() ? Solution::empty(isIdentity: true) : Solution::empty(hasNoRealSolution: true),
            $degree === 1 => $this->solveLinear($get(1), $get(0)),
            $degree === 2 => $this->solveQuadratic($get(2), $get(1), $get(0)),
            default => throw new UnsupportedOperationException("Cannot solve a degree-{$degree} polynomial equation."),
        };
    }

    private function radicalSolver(): RadicalEquationSolver
    {
        return $this->radicalSolver ??= new RadicalEquationSolver();
    }

    /**
     * Like solve(), but also returns a narrated list of Steps describing
     * how the coefficients were extracted and the solution reached.
     */
    public function solveWithSteps(EquationNode $equation, string $variable): SolvedEquation
    {
        try {
            return $this->solvePolynomialWithSteps($equation, $variable);
        } catch (UnsupportedOperationException $polynomialFailure) {
            return $this->radicalSolver()->canHandle($equation, $variable)
                ? $this->radicalSolver()->solveWithSteps($equation, $variable)
                : throw $polynomialFailure;
        }
    }

    private function solvePolynomialWithSteps(EquationNode $equation, string $variable): SolvedEquation
    {
        $steps = [];
        $movedRule = Translatable::of('rule.move_terms', 'Move every term to the left-hand side');
        $movedExpr = $equation->left . ' - (' . $equation->right . ') = 0';
        $steps[] = new Step(
            title: $movedRule,
            currentExpression: (string) $equation,
            rule: $movedRule,
            result: $movedExpr,
            updatedExpression: $movedExpr,
        );

        $difference = new BinaryNode(BinaryOperator::Subtract, $equation->left, $equation->right);
        $coefficients = $this->coefficients->extract($difference, $variable);
        $degree = $coefficients === [] ? 0 : max(array_keys($coefficients));
        $zero = Rational::fromInt(0);
        $get = fn (int $d): Rational => $coefficients[$d] ?? $zero;

        $collectRule = Translatable::of(
            'rule.collect_coefficients',
            'Collect coefficients by power of {variable}',
            ['variable' => $variable]
        );
        $collectedExpr = $this->describeCoefficients($coefficients, $variable) . ' = 0';
        $steps[] = new Step(
            title: $collectRule,
            currentExpression: $movedExpr,
            rule: $collectRule,
            result: $collectedExpr,
            updatedExpression: $collectedExpr,
        );

        $solution = match (true) {
            $degree === 0 => $get(0)->isZero() ? Solution::empty(isIdentity: true) : Solution::empty(hasNoRealSolution: true),
            $degree === 1 => $this->solveLinearWithSteps($get(1), $get(0), $variable, $steps),
            $degree === 2 => $this->solveQuadraticWithSteps($get(2), $get(1), $get(0), $variable, $steps),
            default => throw new UnsupportedOperationException("Cannot solve a degree-{$degree} polynomial equation."),
        };

        return new SolvedEquation($solution, $steps);
    }

    private function describeCoefficients(array $coefficients, string $variable): string
    {
        $parts = [];
        foreach ($coefficients as $degree => $coefficient) {
            if ($coefficient->isZero() && count($coefficients) > 1) {
                continue;
            }
            $parts[] = match (true) {
                $degree === 0 => $coefficient->toMathString(),
                $coefficient->isOne() => $variable . ($degree === 1 ? '' : '^' . $degree),
                $coefficient->negate()->isOne() => '-' . $variable . ($degree === 1 ? '' : '^' . $degree),
                default => $coefficient->toMathString() . $variable . ($degree === 1 ? '' : '^' . $degree),
            };
        }
        return $parts === [] ? '0' : implode(' + ', $parts);
    }

    /** @param Step[] $steps */
    private function solveLinearWithSteps(Rational $a, Rational $b, string $variable, array &$steps): Solution
    {
        $root = $b->negate()->div($a);
        $before = "{$a->toMathString()}{$variable} + {$b->toMathString()} = 0";
        $after = "{$variable} = " . $root->toMathString();
        $rule = Translatable::of(
            'rule.solve_linear',
            'Solve {a}{variable} + {b} = 0 for {variable}',
            ['a' => $a->toMathString(), 'b' => $b->toMathString(), 'variable' => $variable]
        );
        $steps[] = new Step(
            title: $rule,
            currentExpression: $before,
            rule: $rule,
            result: $after,
            updatedExpression: $after,
        );
        return Solution::single($root);
    }

    /** @param Step[] $steps */
    private function solveQuadraticWithSteps(Rational $a, Rational $b, Rational $c, string $variable, array &$steps): Solution
    {
        $discriminant = $b->mul($b)->sub($a->mul($c)->mul(Rational::fromInt(4)));
        $discriminantRule = Translatable::of('rule.discriminant', 'Compute the discriminant b^2 - 4ac');
        $steps[] = new Step(
            title: $discriminantRule,
            currentExpression: "a={$a->toMathString()}, b={$b->toMathString()}, c={$c->toMathString()}",
            rule: $discriminantRule,
            result: 'discriminant = ' . $discriminant->toMathString(),
            updatedExpression: 'discriminant = ' . $discriminant->toMathString(),
            formula: Translatable::of('formula.discriminant', 'b^2 - 4ac'),
        );

        if ($discriminant->isNegative()) {
            $noRealRule = Translatable::of('rule.no_real_roots', 'Discriminant is negative: no real solutions');
            $steps[] = new Step(
                title: $noRealRule,
                currentExpression: $discriminant->toMathString(),
                rule: $noRealRule,
                result: 'no real solutions',
                updatedExpression: 'no real solutions',
            );
            return Solution::empty(hasNoRealSolution: true);
        }

        $sqrtDiscriminant = $discriminant->exactNthRoot(2);
        if ($sqrtDiscriminant === null) {
            throw new UnsupportedOperationException('The roots of this quadratic are irrational; exact solving requires a rational discriminant.');
        }

        $twoA = $a->mul(Rational::fromInt(2));
        $root1 = $b->negate()->add($sqrtDiscriminant)->div($twoA);
        $root2 = $b->negate()->sub($sqrtDiscriminant)->div($twoA);

        $formulaRule = Translatable::of(
            'rule.quadratic_formula',
            'Apply the quadratic formula {variable} = (-b +/- sqrt(discriminant)) / 2a',
            ['variable' => $variable]
        );
        $resultExpr = $root1->equals($root2)
            ? "{$variable} = {$root1->toMathString()}"
            : "{$variable} = {$root1->toMathString()} or {$variable} = {$root2->toMathString()}";
        $steps[] = new Step(
            title: $formulaRule,
            currentExpression: 'discriminant = ' . $discriminant->toMathString(),
            rule: $formulaRule,
            result: $resultExpr,
            updatedExpression: $resultExpr,
            formula: Translatable::of('formula.quadratic', 'x = (-b +/- sqrt(discriminant)) / 2a'),
        );

        return $root1->equals($root2)
            ? Solution::single($root1)
            : new Solution([$root1, $root2]);
    }

    private function solveLinear(Rational $a, Rational $b): Solution
    {
        // a*x + b = 0  =>  x = -b/a
        return Solution::single($b->negate()->div($a));
    }

    private function solveQuadratic(Rational $a, Rational $b, Rational $c): Solution
    {
        // Discriminant = b^2 - 4ac
        $discriminant = $b->mul($b)->sub($a->mul($c)->mul(Rational::fromInt(4)));

        if ($discriminant->isNegative()) {
            return Solution::empty(hasNoRealSolution: true);
        }

        $sqrtDiscriminant = $discriminant->exactNthRoot(2);
        if ($sqrtDiscriminant === null) {
            throw new UnsupportedOperationException('The roots of this quadratic are irrational; exact solving requires a rational discriminant.');
        }

        $twoA = $a->mul(Rational::fromInt(2));
        $root1 = $b->negate()->add($sqrtDiscriminant)->div($twoA);
        $root2 = $b->negate()->sub($sqrtDiscriminant)->div($twoA);

        return $root1->equals($root2)
            ? Solution::single($root1)
            : new Solution([$root1, $root2]);
    }
}
