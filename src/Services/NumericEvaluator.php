<?php
namespace CAS\Services;

use CAS\Nodes\{
    MathNode, NumericNode, IntegerNode, RationalNode, ComplexNode,
    BinaryOperatorNode, PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode,
    PiNode, VariableNode
};

class NumericEvaluator
{
    private SymbolTable $symTable;
    private int $depth = 0;
    private const MAX_DEPTH = 200;

    public function __construct(SymbolTable $symTable)
    {
        $this->symTable = $symTable;
    }

    public function evaluate(MathNode $node): MathNode
    {
        $this->depth = 0;
        return $this->evalNode($node);
    }

    private function evalNode(MathNode $node): MathNode
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw new \RuntimeException('Maximum evaluation depth exceeded.');
        }

        try {
            if ($node instanceof IntegerNode
                || $node instanceof RationalNode
                || $node instanceof ComplexNode
            ) {
                return $node;
            }

            if ($node instanceof PiNode) {
                throw new \RuntimeException('Cannot numerically evaluate symbolic constant π.');
            }

            if ($node instanceof VariableNode) {
                return $this->evalVariable($node);
            }
            if ($node instanceof UnaryNode) {
                return $this->evalUnary($node);
            }
            if ($node instanceof PlusNode)     { return $this->evalAddition($node); }
            if ($node instanceof MinusNode)    { return $this->evalSubtraction($node); }
            if ($node instanceof MultiplyNode) { return $this->evalMultiplication($node); }
            if ($node instanceof DivideNode)   { return $this->evalDivision($node); }
            if ($node instanceof PowerNode)    { return $this->evalPower($node); }
            if ($node instanceof SqrtNode)     { return $this->evalSqrt($node); }
            if ($node instanceof RootNode)     { return $this->evalRoot($node); }

            throw new \RuntimeException('Unsupported node type: ' . get_class($node));
        } finally {
            --$this->depth;
        }
    }

    private function evalVariable(VariableNode $var): MathNode
    {
        $name  = $var->getName();
        $value = $this->symTable->lookup($name);
        if ($value === null) {
            throw new \RuntimeException("Variable '{$name}' is not assigned a value.");
        }
        return $this->evalNode($value);
    }

    private function evalUnary(UnaryNode $u): MathNode
    {
        if ($u->getOp() !== '-') {
            throw new \RuntimeException('Only unary minus is supported in numeric evaluation.');
        }

        $operand = $this->evalNode($u->getOperand());
        $s = $u->getStartPos();
        $e = $u->getEndPos();

        if ($operand instanceof IntegerNode) {
            return new IntegerNode(\gmp_strval(\gmp_neg($operand->getValue())), $s, $e);
        }
        if ($operand instanceof RationalNode) {
            return new RationalNode(
                \gmp_strval(\gmp_neg($operand->getValueOfNumerator())),
                \gmp_strval($operand->getValueOfDenominator()),
                $s, $e
            );
        }
        if ($operand instanceof ComplexNode) {
            return new ComplexNode(
                \gmp_strval(\gmp_neg($operand->getReal())),
                \gmp_strval(\gmp_neg($operand->getImag())),
                $s, $e
            );
        }
        throw new \RuntimeException('Unary minus applied to non-numeric node.');
    }

    private function evalAddition(PlusNode $node): MathNode
    {
        return $this->addValues(
            $this->evalNode($node->getLeft()),
            $this->evalNode($node->getRight()),
            $node->getStartPos(), $node->getEndPos()
        );
    }

    private function evalSubtraction(MinusNode $node): MathNode
    {
        return $this->subtractValues(
            $this->evalNode($node->getLeft()),
            $this->evalNode($node->getRight()),
            $node->getStartPos(), $node->getEndPos()
        );
    }

    private function evalMultiplication(MultiplyNode $node): MathNode
    {
        return $this->multiplyValues(
            $this->evalNode($node->getLeft()),
            $this->evalNode($node->getRight()),
            $node->getStartPos(), $node->getEndPos()
        );
    }

    private function evalDivision(DivideNode $node): MathNode
    {
        return $this->divideValues(
            $this->evalNode($node->getLeft()),
            $this->evalNode($node->getRight()),
            $node->getStartPos(), $node->getEndPos()
        );
    }

    private function evalPower(PowerNode $node): MathNode
    {
        $base = $this->evalNode($node->getLeft());
        $exp  = $this->evalNode($node->getRight());
        if (!$exp instanceof IntegerNode) {
            throw new \RuntimeException('Exponent must be an integer for exact numeric evaluation.');
        }
        return $this->powerValues($base, $exp, $node->getStartPos(), $node->getEndPos());
    }

    private function evalSqrt(SqrtNode $node): MathNode
    {
        $rad = $this->evalNode($node->getRadicand());
        if (!$rad instanceof IntegerNode) {
            throw new \RuntimeException('Square root of non-integer is not supported in exact numeric evaluation.');
        }
        $val = $rad->getValue();
        if (\gmp_cmp($val, 0) < 0) {
            throw new \RuntimeException('Cannot evaluate square root of a negative number.');
        }
        if (!\gmp_perfect_square($val)) {
            throw new \RuntimeException('Cannot evaluate square root of a non-perfect-square exactly.');
        }
        return new IntegerNode(\gmp_strval(\gmp_sqrt($val)), $node->getStartPos(), $node->getEndPos());
    }

    private function evalRoot(RootNode $node): MathNode
    {
        $deg = $this->evalNode($node->getDegree());
        $rad = $this->evalNode($node->getRadicand());

        if (!$deg instanceof IntegerNode || !$rad instanceof IntegerNode) {
            throw new \RuntimeException('Both degree and radicand must be integers for exact root evaluation.');
        }

        $n = (int) \gmp_strval($deg->getValue());
        if ($n < 1) {
            throw new \RuntimeException('Root degree must be positive.');
        }

        $val = $rad->getValue();
        if (\gmp_cmp($val, 0) < 0 && ($n % 2 === 0)) {
            throw new \RuntimeException('Even root of a negative number is not real.');
        }

        $rem = \gmp_rootrem($val, $n);
        if (\gmp_cmp($rem[1], 0) !== 0) {
            throw new \RuntimeException('Radicand is not a perfect power of the given degree.');
        }

        return new IntegerNode(\gmp_strval($rem[0]), $node->getStartPos(), $node->getEndPos());
    }

    // ─── Arithmetic on evaluated nodes ────────────────────────────────

    private function addValues(MathNode $a, MathNode $b, int $s, int $e): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexAdd($a, $b, $s, $e);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        return $this->makeReduced(
            \gmp_add(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1)),
            \gmp_mul($d1, $d2),
            $s, $e
        );
    }

    private function subtractValues(MathNode $a, MathNode $b, int $s, int $e): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexSubtract($a, $b, $s, $e);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        return $this->makeReduced(
            \gmp_sub(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1)),
            \gmp_mul($d1, $d2),
            $s, $e
        );
    }

    private function multiplyValues(MathNode $a, MathNode $b, int $s, int $e): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexMultiply($a, $b, $s, $e);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        return $this->makeReduced(\gmp_mul($n1, $n2), \gmp_mul($d1, $d2), $s, $e);
    }

    private function divideValues(MathNode $a, MathNode $b, int $s, int $e): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexDivide($a, $b, $s, $e);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        if (\gmp_cmp($n2, 0) === 0) {
            throw new \RuntimeException('Division by zero.');
        }
        return $this->makeReduced(\gmp_mul($n1, $d2), \gmp_mul($d1, $n2), $s, $e);
    }

    private function powerValues(MathNode $base, IntegerNode $exp, int $s, int $e): MathNode
    {
        if ($base instanceof ComplexNode) {
            throw new \RuntimeException('Exponentiation of complex numbers is not supported in exact evaluation.');
        }
        $exv = (int) \gmp_strval($exp->getValue());
        [$num, $den] = $this->toRational($base);

        if ($exv >= 0) {
            return $this->makeReduced(\gmp_pow($num, $exv), \gmp_pow($den, $exv), $s, $e);
        }
        $pos = -$exv;
        return $this->makeReduced(\gmp_pow($den, $pos), \gmp_pow($num, $pos), $s, $e);
    }

    // ─── Complex arithmetic ────────────────────────────────────────────

    private function complexAdd(MathNode $a, MathNode $b, int $s, int $e): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        return new ComplexNode(\gmp_strval(\gmp_add($r1, $r2)), \gmp_strval(\gmp_add($i1, $i2)), $s, $e);
    }

    private function complexSubtract(MathNode $a, MathNode $b, int $s, int $e): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        return new ComplexNode(\gmp_strval(\gmp_sub($r1, $r2)), \gmp_strval(\gmp_sub($i1, $i2)), $s, $e);
    }

    private function complexMultiply(MathNode $a, MathNode $b, int $s, int $e): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        // (r1+i1·i)(r2+i2·i) = (r1r2 − i1i2) + (r1i2 + i1r2)i
        return new ComplexNode(
            \gmp_strval(\gmp_sub(\gmp_mul($r1, $r2), \gmp_mul($i1, $i2))),
            \gmp_strval(\gmp_add(\gmp_mul($r1, $i2), \gmp_mul($i1, $r2))),
            $s, $e
        );
    }

    private function complexDivide(MathNode $a, MathNode $b, int $s, int $e): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);

        $denom = \gmp_add(\gmp_mul($r2, $r2), \gmp_mul($i2, $i2));
        if (\gmp_cmp($denom, 0) === 0) {
            throw new \RuntimeException('Division by zero complex number.');
        }

        $numReal = \gmp_add(\gmp_mul($r1, $r2), \gmp_mul($i1, $i2));
        $numImag = \gmp_sub(\gmp_mul($i1, $r2), \gmp_mul($r1, $i2));

        if (\gmp_cmp(\gmp_mod($numReal, $denom), 0) !== 0
            || \gmp_cmp(\gmp_mod($numImag, $denom), 0) !== 0
        ) {
            throw new \RuntimeException(
                'Exact complex division resulted in non-integer components.'
            );
        }

        return new ComplexNode(
            \gmp_strval(\gmp_div_q($numReal, $denom)),
            \gmp_strval(\gmp_div_q($numImag, $denom)),
            $s, $e
        );
    }

    // ─── Conversion helpers ───────────────────────────────────────────

    /** @return array{\GMP, \GMP} */
    private function toRational(MathNode $node): array
    {
        if ($node instanceof IntegerNode)  { return [$node->getValue(), \gmp_init(1)]; }
        if ($node instanceof RationalNode) { return [$node->getValueOfNumerator(), $node->getValueOfDenominator()]; }
        throw new \RuntimeException('Expected a rational or integer node, got ' . get_class($node));
    }

    /** @return array{\GMP, \GMP} */
    private function toComplex(MathNode $node): array
    {
        if ($node instanceof ComplexNode)  { return [$node->getReal(), $node->getImag()]; }
        if ($node instanceof IntegerNode)  { return [$node->getValue(), \gmp_init(0)]; }
        throw new \RuntimeException('Unsupported node type for complex conversion: ' . get_class($node));
    }

    private function makeReduced(\GMP $num, \GMP $den, int $s, int $e): MathNode
    {
        $gcd = \gmp_gcd($num, $den);
        $num = \gmp_div_q($num, $gcd);
        $den = \gmp_div_q($den, $gcd);

        if (\gmp_sign($den) === -1) {
            $num = \gmp_neg($num);
            $den = \gmp_abs($den);
        }

        if (\gmp_cmp($den, 1) === 0) {
            return new IntegerNode(\gmp_strval($num), $s, $e);
        }
        return new RationalNode(\gmp_strval($num), \gmp_strval($den), $s, $e);
    }
}
