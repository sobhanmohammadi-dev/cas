<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, NumericNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, UnaryNode, SqrtNode
};
use Sobhanmohammadi\CAS\Exception\{DomainException, UnsupportedOperationException};

/**
 * Adds quadratic (ax^2 + bx + c = 0) solving to any class that already has
 * `private SymbolTable $symbolTable` and `private Simplifier $simplifier`
 * properties — i.e. SymbolicSolver.
 *
 * Coefficient extraction strategy
 * --------------------------------
 * Rather than writing a general symbolic polynomial expander, this trait
 * samples the (already LHS − RHS simplified) expression at four exact
 * integer points of the unknown, using the existing exact/GMP
 * NumericEvaluator:
 *
 *   f(-1) = a - b + c
 *   f(0)  = c
 *   f(1)  = a + b + c
 *   f(2)  = 4a + 2b + c   (used only to confirm the expression really is
 *                          degree ≤ 2 in the unknown — if the sampled
 *                          f(2) doesn't match what {a,b,c} predict, the
 *                          expression has a higher-degree or otherwise
 *                          unsupported term, and solving is aborted with
 *                          an UnsupportedOperationException)
 *
 * All arithmetic is done in exact GMP rationals, so — unlike a
 * finite-difference scheme over floats — there is no precision loss:
 * {a, b, c} come out exactly equal to the true polynomial coefficients
 * whenever the expression genuinely is quadratic (or lower degree) in
 * the unknown. This does require every *other* variable in the
 * expression to already be bound in the SymbolTable (the unknown itself
 * is temporarily bound to each sample point); if something is still
 * free, evaluation throws UnboundVariableException, which callers should
 * let propagate as "not enough information to solve".
 */
trait QuadraticSolverTrait
{
    /**
     * @return array{0: MathNode, 1: MathNode, 2: MathNode} [a, b, c] as exact numeric nodes
     */
    private function extractQuadraticCoefficients(MathNode $expr, string $unknown): array
    {
        $evaluator = new NumericEvaluator($this->symbolTable);

        $saved = $this->symbolTable->isAssigned($unknown)
            ? $this->symbolTable->lookup($unknown)
            : null;

        try {
            $fNeg1 = $this->sampleRational($evaluator, $expr, $unknown, '-1');
            $f0    = $this->sampleRational($evaluator, $expr, $unknown, '0');
            $f1    = $this->sampleRational($evaluator, $expr, $unknown, '1');
            $f2    = $this->sampleRational($evaluator, $expr, $unknown, '2');

            // c = f(0)
            $c = $f0;
            // b = (f(1) - f(-1)) / 2
            $b = $this->ratDiv($this->ratSub($f1, $fNeg1), [\gmp_init(2), \gmp_init(1)]);
            // a = (f(1) + f(-1) - 2*f(0)) / 2
            $a = $this->ratDiv(
                $this->ratSub($this->ratAdd($f1, $fNeg1), $this->ratScale($f0, 2)),
                [\gmp_init(2), \gmp_init(1)]
            );

            // Confirm degree ≤ 2: predicted f(2) = 4a + 2b + c must equal the sample.
            $predictedF2 = $this->ratAdd(
                $this->ratAdd($this->ratScale($a, 4), $this->ratScale($b, 2)),
                $c
            );
            if (!$this->ratEquals($predictedF2, $f2)) {
                throw new UnsupportedOperationException(
                    "Cannot solve: expression is not a quadratic polynomial in '{$unknown}' "
                    . '(degree higher than 2, or a non-polynomial term such as a variable exponent).'
                );
            }

            if ($this->ratEquals($a, [\gmp_init(0), \gmp_init(1)])) {
                throw new DomainException(
                    "Leading coefficient of '{$unknown}^2' is zero; this is not a quadratic equation."
                );
            }

            return [
                $this->rationalToNode($a),
                $this->rationalToNode($b),
                $this->rationalToNode($c),
            ];
        } finally {
            if ($saved !== null) {
                $this->symbolTable->assign($unknown, $saved);
            } else {
                $this->symbolTable->remove($unknown);
            }
        }
    }

    /**
     * Solves ax^2 + bx + c = 0 given exact numeric coefficient nodes,
     * returning both roots (equal to each other when the discriminant is
     * exactly zero).
     *
     * @return QuadraticRoot[]
     */
    private function solveQuadraticFormula(MathNode $a, MathNode $b, MathNode $c): array
    {
        [$an, $ad] = $this->simplifier->toRationalPair($this->toNumericOrFail($a));
        [$bn, $bd] = $this->simplifier->toRationalPair($this->toNumericOrFail($b));
        [$cn, $cd] = $this->simplifier->toRationalPair($this->toNumericOrFail($c));

        $aR = [$an, $ad];
        $bR = [$bn, $bd];
        $cR = [$cn, $cd];

        // D = b^2 - 4ac
        $bSq   = $this->ratMul($bR, $bR);
        $fourAC = $this->ratScale($this->ratMul($aR, $cR), 4);
        $d      = $this->ratSub($bSq, $fourAC);

        $twoA = $this->ratScale($aR, 2);

        $negBOverTwoA = $this->rationalToNode($this->ratDiv($this->ratNeg($bR), $twoA));

        $dSign = $this->ratCompareToZero($d);

        if ($dSign === 0) {
            $root = $negBOverTwoA;
            $simplified = $this->simplifier->simplifyFully($root);
            return [new QuadraticRoot($simplified), new QuadraticRoot($simplified)];
        }

        if ($dSign > 0) {
            $sqrtDNode = $this->exactOrSymbolicSqrt($d);
            $twoANode  = $this->rationalToNode($twoA);
            $bNode     = $this->rationalToNode($bR);

            $root1 = $this->simplifier->simplifyFully(new DivideNode(
                new PlusNode(new UnaryNode('-', $bNode, 0, 0), $sqrtDNode, 0, 0),
                $twoANode, 0, 0
            ));
            $root2 = $this->simplifier->simplifyFully(new DivideNode(
                new MinusNode(new UnaryNode('-', $bNode, 0, 0), $sqrtDNode, 0, 0),
                $twoANode, 0, 0
            ));
            return [new QuadraticRoot($root1), new QuadraticRoot($root2)];
        }

        // Negative discriminant: complex-conjugate pair.
        $absD        = $this->ratNeg($d);
        $sqrtAbsDNode = $this->exactOrSymbolicSqrt($absD);
        $twoANode     = $this->rationalToNode($twoA);

        $imagPart = $this->simplifier->simplifyFully(new DivideNode($sqrtAbsDNode, $twoANode, 0, 0));
        $realPart = $this->simplifier->simplifyFully($negBOverTwoA);

        return [
            new QuadraticRoot($realPart, $imagPart),
            new QuadraticRoot($realPart, $this->simplifier->simplifyFully(new UnaryNode('-', $imagPart, 0, 0))),
        ];
    }

    // ─── Rational-sampling helpers (exact GMP arithmetic) ────────────────

    /** @return array{0: \GMP, 1: \GMP} */
    private function sampleRational(NumericEvaluator $evaluator, MathNode $expr, string $unknown, string $intVal): array
    {
        $this->symbolTable->assign($unknown, new IntegerNode($intVal, 0, 0));
        $result = $evaluator->evaluate($expr);
        return $this->simplifier->toRationalPair($this->toNumericOrFail($result));
    }

    private function toNumericOrFail(MathNode $node): NumericNode
    {
        if ($node instanceof NumericNode) {
            return $node;
        }
        throw new UnsupportedOperationException(
            'Quadratic coefficient sampling produced a non-numeric result: ' . get_class($node)
        );
    }

    /** @return array{0: \GMP, 1: \GMP} */
    private function ratAdd(array $x, array $y): array
    {
        return $this->ratReduce(\gmp_add(\gmp_mul($x[0], $y[1]), \gmp_mul($y[0], $x[1])), \gmp_mul($x[1], $y[1]));
    }

    private function ratSub(array $x, array $y): array
    {
        return $this->ratReduce(\gmp_sub(\gmp_mul($x[0], $y[1]), \gmp_mul($y[0], $x[1])), \gmp_mul($x[1], $y[1]));
    }

    private function ratMul(array $x, array $y): array
    {
        return $this->ratReduce(\gmp_mul($x[0], $y[0]), \gmp_mul($x[1], $y[1]));
    }

    private function ratDiv(array $x, array $y): array
    {
        if (\gmp_cmp($y[0], 0) === 0) {
            throw new \Sobhanmohammadi\CAS\Exception\DivisionByZeroException('Division by zero while solving quadratic.');
        }
        return $this->ratReduce(\gmp_mul($x[0], $y[1]), \gmp_mul($x[1], $y[0]));
    }

    private function ratScale(array $x, int $k): array
    {
        return $this->ratReduce(\gmp_mul($x[0], \gmp_init($k)), $x[1]);
    }

    private function ratNeg(array $x): array
    {
        return [\gmp_neg($x[0]), $x[1]];
    }

    private function ratEquals(array $x, array $y): bool
    {
        return \gmp_cmp(\gmp_mul($x[0], $y[1]), \gmp_mul($y[0], $x[1])) === 0;
    }

    private function ratCompareToZero(array $x): int
    {
        // denominator is always kept positive by ratReduce
        return \gmp_cmp($x[0], 0);
    }

    /** @return array{0: \GMP, 1: \GMP} */
    private function ratReduce(\GMP $num, \GMP $den): array
    {
        if (\gmp_cmp($den, 0) === 0) {
            throw new \Sobhanmohammadi\CAS\Exception\DivisionByZeroException('Division by zero while solving quadratic.');
        }
        $gcd = \gmp_gcd($num, $den);
        if (\gmp_cmp($gcd, 0) !== 0) {
            $num = \gmp_div_q($num, $gcd);
            $den = \gmp_div_q($den, $gcd);
        }
        if (\gmp_sign($den) === -1) {
            $num = \gmp_neg($num);
            $den = \gmp_abs($den);
        }
        return [$num, $den];
    }

    private function rationalToNode(array $r): MathNode
    {
        return $this->simplifier->makeNumeric($r[0], $r[1], 0, 0);
    }

    /**
     * Builds an exact sqrt(D) node for a non-negative rational D = p/q
     * (q > 0, already lowest terms): returns a plain integer/rational
     * node when D is a perfect square, otherwise a symbolic
     * sqrt(p*q)/q expression (since D = p/q = p*q/q^2), left for the
     * Simplifier to fold further where possible — matching this
     * codebase's existing policy of never approximating irrational
     * roots in the exact layer.
     */
    private function exactOrSymbolicSqrt(array $d): MathNode
    {
        [$num, $den] = $d; // den > 0 guaranteed by ratReduce
        if (\gmp_cmp($num, 0) < 0) {
            throw new DomainException('Cannot take the square root of a negative rational.');
        }
        if (\gmp_perfect_square($num) && \gmp_perfect_square($den)) {
            return $this->simplifier->makeNumeric(\gmp_sqrt($num), \gmp_sqrt($den), 0, 0);
        }
        $radicand = \gmp_mul($num, $den);
        return new DivideNode(
            new SqrtNode(new IntegerNode(\gmp_strval($radicand), 0, 0), 0, 0),
            new IntegerNode(\gmp_strval($den), 0, 0),
            0, 0
        );
    }
}
