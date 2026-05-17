<?php
namespace CAS\Services;

use CAS\Exception\SimplifyException;
use CAS\Nodes\{
    MathNode, NumericNode, IntegerNode, RationalNode, ComplexNode,
    BinaryOperatorNode, PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode,
    PiNode, VariableNode
};

class Simplifier
{
    private SymbolTable $symTable;
    private int $depth;
    private const MAX_DEPTH = 200;
    private const MAX_ITERATIONS = 100;

    public function __construct(SymbolTable $symTable)
    {
        $this->symTable = $symTable;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    public function simplify(MathNode $node): MathNode
    {
        $this->depth = 0;
        return $this->simplifyNode($node);
    }

    public function simplifyFully(MathNode $node): MathNode
    {
        $iteration = 0;
        do {
            $previous = $node;
            $node = $this->simplify($node);
            if (++$iteration > self::MAX_ITERATIONS) {
                throw new SimplifyException(
                    'Simplification did not converge after ' . self::MAX_ITERATIONS . ' iterations.'
                );
            }
        } while (!$this->structuralEquals($node, $previous));
        return $node;
    }

    private function simplifyNode(MathNode $node): MathNode
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw new SimplifyException('Maximum simplification depth exceeded.');
        }

        try {
            if ($node instanceof NumericNode) {
                return $this->simplifyComplex($node);
            }

            if ($node instanceof PiNode) {
                return $node;
            }

            if ($node instanceof VariableNode) {
                return $this->simplifyVariable($node);
            }

            if ($node instanceof UnaryNode) {
                return $this->simplifyUnary($node);
            }

            if ($node instanceof BinaryOperatorNode) {
                return $this->simplifyBinaryOp($node);
            }

            if ($node instanceof SqrtNode) {
                return $this->simplifySqrt($node);
            }
            if ($node instanceof RootNode) {
                return $this->simplifyRoot($node);
            }

            return $node;
        } finally {
            --$this->depth;
        }
    }

    private function simplifyVariable(VariableNode $v): MathNode
    {
        if ($v->isBound()) {
            $val = $v->getAssignedValue();
            return $this->simplifyNode($val);
        }
        $lookup = $this->symTable->lookup($v->getName());
        if ($lookup !== null) {
            return $this->simplifyNode($lookup);
        }
        return $v;
    }

    private function simplifyUnary(UnaryNode $u): MathNode
    {
        $op = $u->getOp();
        $operand = $this->simplifyNode($u->getOperand());

        if ($op !== '-') {
            if ($operand !== $u->getOperand()) {
                return new UnaryNode($op, $operand, $u->getStartPos(), $u->getEndPos());
            }
            return $u;
        }


        // -(-X)  →  X
        if ($operand instanceof UnaryNode && $operand->getOp() === '-') {
            return $operand->getOperand();
        }

        if ($operand instanceof IntegerNode) {
            $val = \gmp_strval(\gmp_neg($operand->getValue()));
            return new IntegerNode($val, $u->getStartPos(), $u->getEndPos());
        }

        if ($operand instanceof RationalNode) {
            $num = \gmp_strval(\gmp_neg($operand->getValueOfNumerator()));
            $den = \gmp_strval($operand->getValueOfDenominator());
            return new RationalNode($num, $den, $u->getStartPos(), $u->getEndPos());
        }

        if ($operand instanceof ComplexNode) {
            $r = \gmp_strval(\gmp_neg($operand->getReal()));
            $i = \gmp_strval(\gmp_neg($operand->getImag()));
            return new ComplexNode($r, $i, $u->getStartPos(), $u->getEndPos());
        }

        if ($operand !== $u->getOperand()) {
            return new UnaryNode('-', $operand, $u->getStartPos(), $u->getEndPos());
        }
        return $u;
    }

    private function simplifyBinaryOp(BinaryOperatorNode $bin): MathNode
    {
        $left = $this->simplifyNode($bin->getLeft());
        $right = $this->simplifyNode($bin->getRight());
        $start = $bin->getStartPos();
        $end = $bin->getEndPos();

        $result = $this->applyIdentity($bin, $left, $right);
        if ($result !== null) {
            return $this->simplifyNode($result);
        }

        $result = $this->applyConstantFolding($bin, $left, $right);
        if ($result !== null) {
            return $result;
        }

        if ($bin instanceof PowerNode) {
            $result = $this->applyPowerRules($bin, $left, $right);
            if ($result !== null) {
                return $this->simplifyNode($result);
            }
        }

        if ($bin instanceof PlusNode || $bin instanceof MinusNode) {
            $result = $this->applyLikeTerms($bin, $left, $right);
            if ($result !== null) {
                return $this->simplifyNode($result);
            }
        }

        if ($bin instanceof MultiplyNode) {
            $result = $this->applyDistribution($bin, $left, $right);
            if ($result !== null) {
                return $this->simplifyNode($result);
            }
        }
        if ($left === $bin->getLeft() && $right === $bin->getRight()) {
            return $bin;
        }

        return $this->makeBinaryOp(get_class($bin), $left, $right, $start, $end);
    }

    private function applyIdentity(BinaryOperatorNode $bin, MathNode $l, MathNode $r): ?MathNode
    {
        if ($bin instanceof PlusNode) {
            if ($this->isNumericZero($l)) return $r;       // 0 + X → X
            if ($this->isNumericZero($r)) return $l;       // X + 0 → X
            return null;
        }
        if ($bin instanceof MinusNode) {
            if ($this->isNumericZero($r)) return $l;       // X - 0 → X
            if ($this->isNumericZero($l)) {                // 0 - X → -X
                return new UnaryNode('-', $r, $bin->getStartPos(), $bin->getEndPos());
            }
            return null;
        }
        if ($bin instanceof MultiplyNode) {
            if ($this->isNumericZero($l) || $this->isNumericZero($r)) {
                return new IntegerNode('0', $bin->getStartPos(), $bin->getEndPos());
            }
            if ($this->isNumericOne($l)) return $r;       // 1 * X → X
            if ($this->isNumericOne($r)) return $l;       // X * 1 → X
            return null;
        }
        if ($bin instanceof DivideNode) {
            if ($this->isNumericZero($l)) {                // 0 / X → 0
                return new IntegerNode('0', $bin->getStartPos(), $bin->getEndPos());
            }
            if ($this->isNumericOne($r)) return $l;       // X / 1 → X
            return null;
        }
        if ($bin instanceof PowerNode) {
            if ($this->isNumericZero($r)) {                // X ^ 0 → 1
                return new IntegerNode('1', $bin->getStartPos(), $bin->getEndPos());
            }
            if ($this->isNumericOne($r)) return $l;       // X ^ 1 → X
            if ($this->isNumericOne($l)) {                 // 1 ^ X → 1
                return new IntegerNode('1', $bin->getStartPos(), $bin->getEndPos());
            }
            if ($this->isNumericZero($l) && !$this->isNumericZero($r)) {
                return new IntegerNode('0', $bin->getStartPos(), $bin->getEndPos());
            }
            return null;
        }
        return null;
    }


    private function applyConstantFolding(BinaryOperatorNode $bin, MathNode $l, MathNode $r): ?MathNode
    {
        if (!$l instanceof NumericNode || !$r instanceof NumericNode) {
            return null;
        }

        if ($l instanceof ComplexNode || $r instanceof ComplexNode) {
            return $this->foldComplex($bin, $l, $r);
        }

        [$n1, $d1] = $this->toRationalArray($l);
        [$n2, $d2] = $this->toRationalArray($r);

        $start = $bin->getStartPos();
        $end   = $bin->getEndPos();

        if ($bin instanceof PlusNode) {
            [$rn, $rd] = $this->rationalAdd($n1, $d1, $n2, $d2);
            return $this->makeNumeric($rn, $rd, $start, $end);
        }
        if ($bin instanceof MinusNode) {
            [$rn, $rd] = $this->rationalSub($n1, $d1, $n2, $d2);
            return $this->makeNumeric($rn, $rd, $start, $end);
        }
        if ($bin instanceof MultiplyNode) {
            [$rn, $rd] = $this->rationalMul($n1, $d1, $n2, $d2);
            return $this->makeNumeric($rn, $rd, $start, $end);
        }
        if ($bin instanceof DivideNode) {
            if (\gmp_cmp($n2, 0) === 0) {
                throw new SimplifyException('Division by zero during constant folding.');
            }
            // (n1/d1) / (n2/d2) = (n1*d2) / (d1*n2)
            [$rn, $rd] = $this->rationalDiv($n1, $d1, $n2, $d2);
            return $this->makeNumeric($rn, $rd, $start, $end);
        }
        if ($bin instanceof PowerNode) {
            if (!$r instanceof IntegerNode) {
                return null;
            }
            $exp = \gmp_intval($r->getValue());
            if ($exp < 0) {
                // a^(-n) = 1 / a^n
                $posExp = (int)\gmp_strval(\gmp_abs($r->getValue()));
                $powNum = \gmp_pow($n1, $posExp);
                $powDen = \gmp_pow($d1, $posExp);
                return $this->makeNumeric($powDen, $powNum, $start, $end);
            }
            $rn = \gmp_pow($n1, $exp);
            $rd = \gmp_pow($d1, $exp);
            return $this->makeNumeric($rn, $rd, $start, $end);
        }
        return null;
    }

    private function foldComplex(BinaryOperatorNode $bin, MathNode $l, MathNode $r): ?MathNode
    {
        if (!$bin instanceof PlusNode && !$bin instanceof MinusNode) {
            return null;
        }

        $lReal = ($l instanceof ComplexNode) ? $l->getReal() : (($l instanceof IntegerNode) ? $l->getValue() : null);
        $lImag = ($l instanceof ComplexNode) ? $l->getImag() : \gmp_init(0);
        $rReal = ($r instanceof ComplexNode) ? $r->getReal() : (($r instanceof IntegerNode) ? $r->getValue() : null);
        $rImag = ($r instanceof ComplexNode) ? $r->getImag() : \gmp_init(0);

        if ($lReal === null || $rReal === null) {
            return null;
        }

        if ($bin instanceof PlusNode) {
            $real = \gmp_add($lReal, $rReal);
            $imag = \gmp_add($lImag, $rImag);
        } else {
            $real = \gmp_sub($lReal, $rReal);
            $imag = \gmp_sub($lImag, $rImag);
        }

        $start = $bin->getStartPos();
        $end   = $bin->getEndPos();

        if (\gmp_cmp($imag, 0) === 0) {
            return new IntegerNode(\gmp_strval($real), $start, $end);
        }
        return new ComplexNode(\gmp_strval($real), \gmp_strval($imag), $start, $end);
    }

    private function applyPowerRules(PowerNode $pow, MathNode $l, MathNode $r): ?MathNode
    {
        // (X ^ a) ^ b  →  X ^ (a * b)
        if ($l instanceof PowerNode) {
            $innerBase = $l->getLeft();
            $innerExp  = $l->getRight();
            if ($innerExp instanceof IntegerNode && $r instanceof IntegerNode) {
                $newExp = \gmp_mul($innerExp->getValue(), $r->getValue());
                $start = $pow->getStartPos();
                $end   = $pow->getEndPos();
                return new PowerNode(
                    $innerBase,
                    new IntegerNode(\gmp_strval($newExp), $start, $end),
                    $start,
                    $end
                );
            }
        }
        return null;
    }

    private function applyLikeTerms(BinaryOperatorNode $bin, MathNode $l, MathNode $r): ?MathNode
    {
        [$c1, $t1] = $this->extractCoefficientAndTerm($l);
        [$c2, $t2] = $this->extractCoefficientAndTerm($r);

        if ($c1 === null || $c2 === null) {
            return null;
        }

        if (!$this->structuralEquals($t1, $t2)) {
            return null;
        }

        $start = $bin->getStartPos();
        $end   = $bin->getEndPos();

        [$n1, $d1] = $this->toRationalArray($c1);
        [$n2, $d2] = $this->toRationalArray($c2);

        if ($bin instanceof PlusNode) {
            [$cn, $cd] = $this->rationalAdd($n1, $d1, $n2, $d2);
        } else {
            [$cn, $cd] = $this->rationalSub($n1, $d1, $n2, $d2);
        }

        if (\gmp_cmp($cn, 0) === 0) {
            return new IntegerNode('0', $start, $end);
        }

        $newCoeff = $this->makeNumeric($cn, $cd, $start, $end);

        if ($this->isNumericOne($newCoeff) && !$this->isNumericOne($t1)) {
            return $t1;
        }

        if ($this->isNumericOne($t1)) {
            return $newCoeff;
        }

        return new MultiplyNode($newCoeff, $t1, $start, $end);
    }

    private function applyDistribution(BinaryOperatorNode $bin, MathNode $l, MathNode $r): ?MathNode
    {
        $start = $bin->getStartPos();
        $end   = $bin->getEndPos();

        // a * (b + c)  →  a*b + a*c
        if ($r instanceof PlusNode) {
            $a = $l;
            $b = $r->getLeft();
            $c = $r->getRight();
            return new PlusNode(
                new MultiplyNode($a, $b, $start, $end),
                new MultiplyNode($a, $c, $start, $end),
                $start,
                $end
            );
        }
        // a * (b - c)  →  a*b - a*c
        if ($r instanceof MinusNode) {
            $a = $l;
            $b = $r->getLeft();
            $c = $r->getRight();
            return new MinusNode(
                new MultiplyNode($a, $b, $start, $end),
                new MultiplyNode($a, $c, $start, $end),
                $start,
                $end
            );
        }
        // (b + c) * a  →  b*a + c*a
        if ($l instanceof PlusNode) {
            $a = $r;
            $b = $l->getLeft();
            $c = $l->getRight();
            return new PlusNode(
                new MultiplyNode($b, $a, $start, $end),
                new MultiplyNode($c, $a, $start, $end),
                $start,
                $end
            );
        }
        // (b - c) * a  →  b*a - c*a
        if ($l instanceof MinusNode) {
            $a = $r;
            $b = $l->getLeft();
            $c = $l->getRight();
            return new MinusNode(
                new MultiplyNode($b, $a, $start, $end),
                new MultiplyNode($c, $a, $start, $end),
                $start,
                $end
            );
        }
        return null;
    }

    private function simplifySqrt(SqrtNode $s): MathNode
    {
        $rad = $this->simplifyNode($s->getRadicand());
        $start = $s->getStartPos();
        $end   = $s->getEndPos();

        if ($rad instanceof IntegerNode) {
            $val = $rad->getValue();
            if (\gmp_cmp($val, 0) >= 0 && \gmp_perfect_square($val)) {
                $root = \gmp_sqrt($val);
                return new IntegerNode(\gmp_strval($root), $start, $end);
            }
        }

        if ($rad instanceof PowerNode) {
            $base = $rad->getLeft();
            $exp  = $rad->getRight();
            if ($exp instanceof IntegerNode && \gmp_cmp($exp->getValue(), 2) === 0) {
                if ($base instanceof IntegerNode && \gmp_cmp($base->getValue(), 0) >= 0) {
                    return $base;
                }
            }
        }

        if ($rad !== $s->getRadicand()) {
            return new SqrtNode($rad, $start, $end);
        }
        return $s;
    }

    private function simplifyRoot(RootNode $r): MathNode
    {
        $deg = $this->simplifyNode($r->getDegree());
        $rad = $this->simplifyNode($r->getRadicand());
        $start = $r->getStartPos();
        $end   = $r->getEndPos();

        // root(X, 1)  →  X
        if ($deg instanceof IntegerNode && \gmp_cmp($deg->getValue(), 1) === 0) {
            return $rad;
        }

        if ($rad instanceof IntegerNode && $deg instanceof IntegerNode) {
            $nth = (int)\gmp_strval($deg->getValue());
            if ($nth > 1) {
                $val = $rad->getValue();
                if (\gmp_cmp($val, 0) >= 0) {
                    $rem = \gmp_rootrem($val, $nth);
                    if (\gmp_cmp($rem[1], 0) === 0) {
                        return new IntegerNode(\gmp_strval($rem[0]), $start, $end);
                    }
                }
            }
        }

        if ($deg !== $r->getDegree() || $rad !== $r->getRadicand()) {
            return new RootNode($deg, $rad, $start, $end);
        }
        return $r;
    }

    private function simplifyComplex(MathNode $node): MathNode
    {
        if (!$node instanceof ComplexNode) {
            return $node;
        }

        $real = $node->getReal();
        $imag = $node->getImag();

        // 0 + 0i  →  0
        if (\gmp_cmp($real, 0) === 0 && \gmp_cmp($imag, 0) === 0) {
            return new IntegerNode('0', $node->getStartPos(), $node->getEndPos());
        }

        // a + 0i  →  a
        if (\gmp_cmp($imag, 0) === 0) {
            return new IntegerNode(\gmp_strval($real), $node->getStartPos(), $node->getEndPos());
        }

        return $node;
    }


    private function structuralEquals(MathNode $a, MathNode $b): bool
    {
        if (get_class($a) !== get_class($b)) {
            return false;
        }

        if ($a instanceof IntegerNode) {
            return \gmp_cmp($a->getValue(), $b->getValue()) === 0;
        }
        if ($a instanceof RationalNode) {
            return \gmp_cmp($a->getValueOfNumerator(), $b->getValueOfNumerator()) === 0
                && \gmp_cmp($a->getValueOfDenominator(), $b->getValueOfDenominator()) === 0;
        }
        if ($a instanceof ComplexNode) {
            return \gmp_cmp($a->getReal(), $b->getReal()) === 0
                && \gmp_cmp($a->getImag(), $b->getImag()) === 0;
        }
        if ($a instanceof PiNode) {
            return true;
        }
        if ($a instanceof VariableNode) {
            return $a->getName() === $b->getName();
        }

        if ($a instanceof UnaryNode) {
            return $a->getOp() === $b->getOp()
                && $this->structuralEquals($a->getOperand(), $b->getOperand());
        }

        if ($a instanceof PlusNode || $a instanceof MultiplyNode) {
            return ($this->structuralEquals($a->getLeft(), $b->getLeft())
                    && $this->structuralEquals($a->getRight(), $b->getRight()))
                || ($this->structuralEquals($a->getLeft(), $b->getRight())
                    && $this->structuralEquals($a->getRight(), $b->getLeft()));
        }
        if ($a instanceof BinaryOperatorNode) {
            return $this->structuralEquals($a->getLeft(), $b->getLeft())
                && $this->structuralEquals($a->getRight(), $b->getRight());
        }

        if ($a instanceof SqrtNode) {
            return $this->structuralEquals($a->getRadicand(), $b->getRadicand());
        }
        if ($a instanceof RootNode) {
            return $this->structuralEquals($a->getDegree(), $b->getDegree())
                && $this->structuralEquals($a->getRadicand(), $b->getRadicand());
        }

        return false;
    }

    private function isNumericZero(MathNode $n): bool
    {
        if ($n instanceof IntegerNode) {
            return \gmp_cmp($n->getValue(), 0) === 0;
        }
        if ($n instanceof RationalNode) {
            return \gmp_cmp($n->getValueOfNumerator(), 0) === 0;
        }
        if ($n instanceof ComplexNode) {
            return \gmp_cmp($n->getReal(), 0) === 0 && \gmp_cmp($n->getImag(), 0) === 0;
        }
        return false;
    }

    private function isNumericOne(MathNode $n): bool
    {
        if ($n instanceof IntegerNode) {
            return \gmp_cmp($n->getValue(), 1) === 0;
        }
        if ($n instanceof RationalNode) {
            return \gmp_cmp($n->getValueOfNumerator(), $n->getValueOfDenominator()) === 0
                && \gmp_cmp($n->getValueOfNumerator(), 0) !== 0;
        }
        if ($n instanceof ComplexNode) {
            return \gmp_cmp($n->getReal(), 1) === 0 && \gmp_cmp($n->getImag(), 0) === 0;
        }
        return false;
    }

    private function extractCoefficientAndTerm(MathNode $n): array
    {
        $start = -1;
        $end   = -1;

        if ($n instanceof NumericNode) {
            return [$n, new IntegerNode('1', $start, $end)];
        }

        if ($n instanceof VariableNode || $n instanceof PiNode) {
            return [new IntegerNode('1', $start, $end), $n];
        }

        // -X  →  (-1, X)
        if ($n instanceof UnaryNode && $n->getOp() === '-') {
            $inner = $n->getOperand();
            if ($inner instanceof NumericNode) {
                return [$n->getOperand(), new IntegerNode('1', $start, $end)];
            }
            return [new IntegerNode('-1', $start, $end), $inner];
        }

        // c * X  →  (c, X)
        if ($n instanceof MultiplyNode) {
            $l = $n->getLeft();
            $r = $n->getRight();
            if ($l instanceof NumericNode) {
                return [$l, $r];
            }
            if ($r instanceof NumericNode) {
                return [$r, $l];
            }
            return [null, $n];
        }

        // X ^ k  →  (1, X^k)
        if ($n instanceof PowerNode) {
            return [new IntegerNode('1', $start, $end), $n];
        }

        // Sqrt / Root
        if ($n instanceof SqrtNode || $n instanceof RootNode) {
            return [new IntegerNode('1', $start, $end), $n];
        }

        return [null, $n];
    }

    private function toRationalArray(MathNode $n): array
    {
        if ($n instanceof IntegerNode) {
            return [$n->getValue(), \gmp_init(1)];
        }
        if ($n instanceof RationalNode) {
            return [$n->getValueOfNumerator(), $n->getValueOfDenominator()];
        }
        throw new SimplifyException('Cannot convert node to rational: ' . get_class($n));
    }

    private function makeNumeric(\GMP $num, \GMP $den, int $start, int $end): NumericNode
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

    private function makeBinaryOp(string $class, MathNode $l, MathNode $r, int $start, int $end): BinaryOperatorNode
    {
        return new $class($l, $r, $start, $end);
    }
    private function rationalAdd(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        $num = \gmp_add(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1));
        $den = \gmp_mul($d1, $d2);
        return [$num, $den];
    }

    private function rationalSub(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        $num = \gmp_sub(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1));
        $den = \gmp_mul($d1, $d2);
        return [$num, $den];
    }

    private function rationalMul(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        $num = \gmp_mul($n1, $n2);
        $den = \gmp_mul($d1, $d2);
        return [$num, $den];
    }

    private function rationalDiv(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        // (n1/d1) / (n2/d2) = (n1*d2) / (d1*n2)
        $num = \gmp_mul($n1, $d2);
        $den = \gmp_mul($d1, $n2);
        if (\gmp_cmp($den, 0) === 0) {
            throw new SimplifyException('Division by zero.');
        }
        return [$num, $den];
    }
}