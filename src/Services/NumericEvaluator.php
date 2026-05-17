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
    private int $depth;
    private const MAX_DEPTH = 200;

    public function __construct(SymbolTable $symTable)
    {
        $this->symTable = $symTable;
    }

    public function evaluate(MathNode $node): MathNode
    {
        $this->depth = 0;
        return $this->evaluateNode($node);
    }

    private function evaluateNode(MathNode $node): MathNode
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw new \RuntimeException('Maximum evaluation depth exceeded.');
        }

        try {
            if ($node instanceof IntegerNode || $node instanceof RationalNode || $node instanceof ComplexNode) {
                return $node;
            }

            if ($node instanceof PiNode) {
                throw new \RuntimeException('Cannot numerically evaluate symbolic constant π.');
            }

            if ($node instanceof VariableNode) {
                return $this->evaluateVariable($node);
            }

            if ($node instanceof UnaryNode) {
                return $this->evaluateUnary($node);
            }

            if ($node instanceof PlusNode) {
                return $this->evaluateAddition($node);
            }
            if ($node instanceof MinusNode) {
                return $this->evaluateSubtraction($node);
            }
            if ($node instanceof MultiplyNode) {
                return $this->evaluateMultiplication($node);
            }
            if ($node instanceof DivideNode) {
                return $this->evaluateDivision($node);
            }
            if ($node instanceof PowerNode) {
                return $this->evaluatePower($node);
            }

            if ($node instanceof SqrtNode) {
                return $this->evaluateSqrt($node);
            }
            if ($node instanceof RootNode) {
                return $this->evaluateRoot($node);
            }

            throw new \RuntimeException('Unsupported node type: ' . get_class($node));
        } finally {
            --$this->depth;
        }
    }

    private function evaluateVariable(VariableNode $var): MathNode
    {
        $name = $var->getName();
        $value = $this->symTable->lookup($name);

        if ($value === null) {
            throw new \RuntimeException("Variable '{$name}' is not assigned a value.");
        }

        return $this->evaluateNode($value);
    }

    private function evaluateUnary(UnaryNode $unary): MathNode
    {
        $operand = $this->evaluateNode($unary->getOperand());

        if ($unary->getOp() !== '-') {
            throw new \RuntimeException('Only unary minus is supported in numeric evaluation.');
        }

        if ($operand instanceof IntegerNode) {
            return new IntegerNode(
                \gmp_strval(\gmp_neg($operand->getValue())),
                $unary->getStartPos(),
                $unary->getEndPos()
            );
        }

        if ($operand instanceof RationalNode) {
            return new RationalNode(
                \gmp_strval(\gmp_neg($operand->getValueOfNumerator())),
                \gmp_strval($operand->getValueOfDenominator()),
                $unary->getStartPos(),
                $unary->getEndPos()
            );
        }

        if ($operand instanceof ComplexNode) {
            return new ComplexNode(
                \gmp_strval(\gmp_neg($operand->getReal())),
                \gmp_strval(\gmp_neg($operand->getImag())),
                $unary->getStartPos(),
                $unary->getEndPos()
            );
        }

        throw new \RuntimeException('Unary minus applied to non-numeric node.');
    }

    private function evaluateAddition(PlusNode $node): MathNode
    {
        $left  = $this->evaluateNode($node->getLeft());
        $right = $this->evaluateNode($node->getRight());

        return $this->addValues($left, $right, $node->getStartPos(), $node->getEndPos());
    }

    private function evaluateSubtraction(MinusNode $node): MathNode
    {
        $left  = $this->evaluateNode($node->getLeft());
        $right = $this->evaluateNode($node->getRight());

        return $this->subtractValues($left, $right, $node->getStartPos(), $node->getEndPos());
    }

    private function evaluateMultiplication(MultiplyNode $node): MathNode
    {
        $left  = $this->evaluateNode($node->getLeft());
        $right = $this->evaluateNode($node->getRight());

        return $this->multiplyValues($left, $right, $node->getStartPos(), $node->getEndPos());
    }

    private function evaluateDivision(DivideNode $node): MathNode
    {
        $left  = $this->evaluateNode($node->getLeft());
        $right = $this->evaluateNode($node->getRight());

        return $this->divideValues($left, $right, $node->getStartPos(), $node->getEndPos());
    }

    private function evaluatePower(PowerNode $node): MathNode
    {
        $base = $this->evaluateNode($node->getLeft());
        $exp  = $this->evaluateNode($node->getRight());

        if (!$exp instanceof IntegerNode) {
            throw new \RuntimeException('Exponent must be an integer for numeric evaluation.');
        }

        return $this->powerValues($base, $exp, $node->getStartPos(), $node->getEndPos());
    }

    private function evaluateSqrt(SqrtNode $node): MathNode
    {
        $rad = $this->evaluateNode($node->getRadicand());

        if ($rad instanceof IntegerNode) {
            $val = $rad->getValue();
            if (\gmp_cmp($val, 0) < 0) {
                throw new \RuntimeException('Cannot evaluate square root of a negative number.');
            }
            if (\gmp_perfect_square($val)) {
                $root = \gmp_sqrt($val);
                return new IntegerNode(\gmp_strval($root), $node->getStartPos(), $node->getEndPos());
            }
            throw new \RuntimeException('Cannot evaluate square root of non-perfect square exactly.');
        }

        throw new \RuntimeException('Square root of non-integer is not supported in exact numeric evaluation.');
    }

    private function evaluateRoot(RootNode $node): MathNode
    {
        $deg = $this->evaluateNode($node->getDegree());
        $rad = $this->evaluateNode($node->getRadicand());

        if (!$deg instanceof IntegerNode || !$rad instanceof IntegerNode) {
            throw new \RuntimeException('Both degree and radicand must be integers for exact root evaluation.');
        }

        $n = (int)\gmp_strval($deg->getValue());
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


    private function addValues(MathNode $a, MathNode $b, int $start, int $end): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexAdd($a, $b, $start, $end);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        $num = \gmp_add(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1));
        $den = \gmp_mul($d1, $d2);
        return $this->makeReduced($num, $den, $start, $end);
    }

    private function subtractValues(MathNode $a, MathNode $b, int $start, int $end): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexSubtract($a, $b, $start, $end);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        $num = \gmp_sub(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1));
        $den = \gmp_mul($d1, $d2);
        return $this->makeReduced($num, $den, $start, $end);
    }

    private function multiplyValues(MathNode $a, MathNode $b, int $start, int $end): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexMultiply($a, $b, $start, $end);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        $num = \gmp_mul($n1, $n2);
        $den = \gmp_mul($d1, $d2);
        return $this->makeReduced($num, $den, $start, $end);
    }

    private function divideValues(MathNode $a, MathNode $b, int $start, int $end): MathNode
    {
        if ($a instanceof ComplexNode || $b instanceof ComplexNode) {
            return $this->complexDivide($a, $b, $start, $end);
        }
        [$n1, $d1] = $this->toRational($a);
        [$n2, $d2] = $this->toRational($b);
        if (\gmp_cmp($n2, 0) === 0) {
            throw new \RuntimeException('Division by zero.');
        }
        $num = \gmp_mul($n1, $d2);
        $den = \gmp_mul($d1, $n2);
        return $this->makeReduced($num, $den, $start, $end);
    }

    private function powerValues(MathNode $base, IntegerNode $exp, int $start, int $end): MathNode
    {
        if ($base instanceof ComplexNode) {
            throw new \RuntimeException('Exponentiation of complex numbers is not supported in exact evaluation.');
        }

        $e = (int)\gmp_strval($exp->getValue());
        [$num, $den] = $this->toRational($base);

        if ($e >= 0) {
            $powNum = \gmp_pow($num, $e);
            $powDen = \gmp_pow($den, $e);
        } else {
            // a^(-n) = 1 / a^n
            $posExp = -$e;
            $powNum = \gmp_pow($den, $posExp);
            $powDen = \gmp_pow($num, $posExp);
        }

        return $this->makeReduced($powNum, $powDen, $start, $end);
    }

    private function complexAdd(MathNode $a, MathNode $b, int $start, int $end): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        return new ComplexNode(
            \gmp_strval(\gmp_add($r1, $r2)),
            \gmp_strval(\gmp_add($i1, $i2)),
            $start, $end
        );
    }

    private function complexSubtract(MathNode $a, MathNode $b, int $start, int $end): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        return new ComplexNode(
            \gmp_strval(\gmp_sub($r1, $r2)),
            \gmp_strval(\gmp_sub($i1, $i2)),
            $start, $end
        );
    }

    private function complexMultiply(MathNode $a, MathNode $b, int $start, int $end): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);
        // (r1 + i1 i)(r2 + i2 i) = (r1 r2 - i1 i2) + i (r1 i2 + i1 r2)
        $real = \gmp_sub(\gmp_mul($r1, $r2), \gmp_mul($i1, $i2));
        $imag = \gmp_add(\gmp_mul($r1, $i2), \gmp_mul($i1, $r2));
        return new ComplexNode(
            \gmp_strval($real),
            \gmp_strval($imag),
            $start, $end
        );
    }

    private function complexDivide(MathNode $a, MathNode $b, int $start, int $end): ComplexNode
    {
        [$r1, $i1] = $this->toComplex($a);
        [$r2, $i2] = $this->toComplex($b);

        $denReal = \gmp_add(\gmp_mul($r2, $r2), \gmp_mul($i2, $i2));
        if (\gmp_cmp($denReal, 0) === 0) {
            throw new \RuntimeException('Division by zero complex number.');
        }

        $numReal = \gmp_add(\gmp_mul($r1, $r2), \gmp_mul($i1, $i2));
        $numImag = \gmp_sub(\gmp_mul($i1, $r2), \gmp_mul($r1, $i2));

        if (\gmp_cmp(\gmp_mod($numReal, $denReal), 0) !== 0 ||
            \gmp_cmp(\gmp_mod($numImag, $denReal), 0) !== 0) {
            throw new \RuntimeException(
                'Exact complex division resulted in non-integer components. Exact rational complex not supported.'
            );
        }

        $real = \gmp_div_q($numReal, $denReal);
        $imag = \gmp_div_q($numImag, $denReal);

        return new ComplexNode(
            \gmp_strval($real),
            \gmp_strval($imag),
            $start,
            $end
        );
    }

    private function toRational(MathNode $node): array
    {
        if ($node instanceof IntegerNode) {
            return [$node->getValue(), \gmp_init(1)];
        }
        if ($node instanceof RationalNode) {
            return [$node->getValueOfNumerator(), $node->getValueOfDenominator()];
        }
        throw new \RuntimeException('Expected a rational or integer node, got ' . get_class($node));
    }

    private function toComplex(MathNode $node): array
    {
        if ($node instanceof ComplexNode) {
            return [$node->getReal(), $node->getImag()];
        }
        if ($node instanceof IntegerNode) {
            return [$node->getValue(), \gmp_init(0)];
        }
        if ($node instanceof RationalNode) {
            throw new \RuntimeException('Complex arithmetic with non-integer real parts is not supported in exact mode. Convert to integer or rational complex.');
        }
        throw new \RuntimeException('Unsupported node type for complex conversion: ' . get_class($node));
    }

    private function makeReduced(\GMP $num, \GMP $den, int $start, int $end): MathNode
    {
        $gcd = \gmp_gcd($num, $den);
        $num = \gmp_div_q($num, $gcd);
        $den = \gmp_div_q($den, $gcd);

        if (\gmp_sign($den) === -1) {
            $num = \gmp_neg($num);
            $den = \gmp_abs($den);
        }

        if (\gmp_cmp($den, 1) === 0) {
            return new IntegerNode(\gmp_strval($num), $start, $end);
        }
        return new RationalNode(\gmp_strval($num), \gmp_strval($den), $start, $end);
    }
}