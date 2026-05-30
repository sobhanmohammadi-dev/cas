<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Exception\SimplifyException;
use Sobhanmohammadi\CAS\Nodes\{
    MathNode, NumericNode, IntegerNode, RationalNode, ComplexNode,
    BinaryOperatorNode, PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode,
    PiNode, VariableNode
};

class Simplifier
{
    private SymbolTable $symTable;
    private int $depth = 0;

    /** @var SimplifierObserver|null */
    private ?SimplifierObserver $observer = null;

    private const MAX_DEPTH      = 200;
    private const MAX_ITERATIONS = 100;

    public function __construct(SymbolTable $symTable)
    {
        $this->symTable = $symTable;
    }

    public function setObserver(?SimplifierObserver $observer): void
    {
        $this->observer = $observer;
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
            $node     = $this->simplify($node);
            if (++$iteration > self::MAX_ITERATIONS) {
                throw new SimplifyException(
                    'Simplification did not converge after ' . self::MAX_ITERATIONS . ' iterations.'
                );
            }
        } while (!$this->structuralEquals($node, $previous));
        return $node;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CORE RECURSIVE SIMPLIFIER
    // ═══════════════════════════════════════════════════════════════════

    private function simplifyNode(MathNode $node): MathNode
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw new SimplifyException('Maximum simplification depth exceeded.');
        }

        try {
            if ($node instanceof NumericNode) {
                return $this->simplifyNumeric($node);
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

    // ─── Leaf simplifications ─────────────────────────────────────────

    private function simplifyNumeric(NumericNode $node): MathNode
    {
        if (!$node instanceof ComplexNode) {
            return $node;
        }
        // (a + 0i) → a,  (0 + 0i) → 0
        if (\gmp_cmp($node->getImag(), 0) === 0) {
            return new IntegerNode(\gmp_strval($node->getReal()), $node->getStartPos(), $node->getEndPos());
        }
        return $node;
    }

    private function simplifyVariable(VariableNode $v): MathNode
    {
        $lookup = $this->symTable->lookup($v->getName());
        if ($lookup !== null) {
            return $this->simplifyNode($lookup);
        }
        return $v;
    }

    private function simplifyUnary(UnaryNode $u): MathNode
    {
        $op      = $u->getOp();
        $operand = $this->simplifyNode($u->getOperand());

        if ($op !== '-') {
            return $operand !== $u->getOperand()
                ? new UnaryNode($op, $operand, $u->getStartPos(), $u->getEndPos())
                : $u;
        }

        // -(-X) → X
        if ($operand instanceof UnaryNode && $operand->getOp() === '-') {
            $result = $operand->getOperand();
            $this->notify('double negation', $u, $result);
            return $result;
        }

        if ($operand instanceof IntegerNode) {
            return new IntegerNode(
                \gmp_strval(\gmp_neg($operand->getValue())),
                $u->getStartPos(), $u->getEndPos()
            );
        }
        if ($operand instanceof RationalNode) {
            return new RationalNode(
                \gmp_strval(\gmp_neg($operand->getValueOfNumerator())),
                \gmp_strval($operand->getValueOfDenominator()),
                $u->getStartPos(), $u->getEndPos()
            );
        }
        if ($operand instanceof ComplexNode) {
            return new ComplexNode(
                \gmp_strval(\gmp_neg($operand->getReal())),
                \gmp_strval(\gmp_neg($operand->getImag())),
                $u->getStartPos(), $u->getEndPos()
            );
        }

        return $operand !== $u->getOperand()
            ? new UnaryNode('-', $operand, $u->getStartPos(), $u->getEndPos())
            : $u;
    }

    // ─── Binary operators ─────────────────────────────────────────────

    private function simplifyBinaryOp(BinaryOperatorNode $bin): MathNode
    {
        $left  = $this->simplifyNode($bin->getLeft());
        $right = $this->simplifyNode($bin->getRight());
        $start = $bin->getStartPos();
        $end   = $bin->getEndPos();

        // 1. Identity / annihilation rules
        $result = $this->applyIdentity($bin, $left, $right, $start, $end);
        if ($result !== null) {
            $this->notify('identity/annihilation', $bin, $result);
            return $this->simplifyNode($result);
        }

        // 2. Constant folding (both children are numeric)
        if ($left instanceof NumericNode && $right instanceof NumericNode) {
            $folded = $this->applyConstantFolding($bin, $left, $right, $start, $end);
            if ($folded !== null) {
                $this->notify('constant fold', $bin, $folded);
                return $folded;
            }
        }

        // 3. Power rules: (X^a)^b → X^(a*b)
        if ($bin instanceof PowerNode) {
            $result = $this->applyPowerRules($left, $right, $start, $end);
            if ($result !== null) {
                $this->notify('power rule', $bin, $result);
                return $this->simplifyNode($result);
            }
        }

        // 4. Like-terms combination for addition/subtraction
        if ($bin instanceof PlusNode || $bin instanceof MinusNode) {
            $result = $this->applyLikeTerms($bin, $left, $right, $start, $end);
            if ($result !== null) {
                $this->notify('combine like terms', $bin, $result);
                return $this->simplifyNode($result);
            }
        }

        // 5. Distribution: a*(b±c) → a*b ± a*c
        if ($bin instanceof MultiplyNode) {
            $result = $this->applyDistribution($left, $right, $start, $end);
            if ($result !== null) {
                $this->notify('distribution', $bin, $result);
                return $this->simplifyNode($result);
            }
        }

        // Rebuild node only if children changed
        if ($left === $bin->getLeft() && $right === $bin->getRight()) {
            return $bin;
        }
        return $this->makeBinaryOp(get_class($bin), $left, $right, $start, $end);
    }

    private function applyIdentity(
        BinaryOperatorNode $bin,
        MathNode $l,
        MathNode $r,
        int $s,
        int $e
    ): ?MathNode {
        if ($bin instanceof PlusNode) {
            if ($this->isNumericZero($l)) return $r;
            if ($this->isNumericZero($r)) return $l;
            return null;
        }
        if ($bin instanceof MinusNode) {
            if ($this->isNumericZero($r)) return $l;
            if ($this->isNumericZero($l)) return new UnaryNode('-', $r, $s, $e);
            return null;
        }
        if ($bin instanceof MultiplyNode) {
            if ($this->isNumericZero($l) || $this->isNumericZero($r)) {
                return new IntegerNode('0', $s, $e);
            }
            if ($this->isNumericOne($l)) return $r;
            if ($this->isNumericOne($r)) return $l;
            return null;
        }
        if ($bin instanceof DivideNode) {
            if ($this->isNumericZero($l)) return new IntegerNode('0', $s, $e);
            if ($this->isNumericOne($r))  return $l;
            return null;
        }
        if ($bin instanceof PowerNode) {
            if ($this->isNumericZero($r))                             return new IntegerNode('1', $s, $e);
            if ($this->isNumericOne($r))                              return $l;
            if ($this->isNumericOne($l))                              return new IntegerNode('1', $s, $e);
            if ($this->isNumericZero($l) && !$this->isNumericZero($r)) return new IntegerNode('0', $s, $e);
            return null;
        }
        return null;
    }

    private function applyConstantFolding(
        BinaryOperatorNode $bin,
        NumericNode $l,
        NumericNode $r,
        int $s,
        int $e
    ): ?MathNode {
        // Complex arithmetic is limited to +/-
        if ($l instanceof ComplexNode || $r instanceof ComplexNode) {
            return $this->foldComplex($bin, $l, $r, $s, $e);
        }

        [$n1, $d1] = $this->toRationalPair($l);
        [$n2, $d2] = $this->toRationalPair($r);

        if ($bin instanceof PlusNode) {
            [$rn, $rd] = $this->rationalAdd($n1, $d1, $n2, $d2);
        } elseif ($bin instanceof MinusNode) {
            [$rn, $rd] = $this->rationalSub($n1, $d1, $n2, $d2);
        } elseif ($bin instanceof MultiplyNode) {
            [$rn, $rd] = $this->rationalMul($n1, $d1, $n2, $d2);
        } elseif ($bin instanceof DivideNode) {
            if (\gmp_cmp($n2, 0) === 0) {
                throw new SimplifyException('Division by zero during constant folding.');
            }
            [$rn, $rd] = $this->rationalDiv($n1, $d1, $n2, $d2);
        } elseif ($bin instanceof PowerNode) {
            if (!$r instanceof IntegerNode) {
                return null;    // non-integer exponent – leave symbolic
            }
            $exp = (int) \gmp_strval($r->getValue());
            if ($exp >= 0) {
                $rn = \gmp_pow($n1, $exp);
                $rd = \gmp_pow($d1, $exp);
            } else {
                $pos = -$exp;
                $rn  = \gmp_pow($d1, $pos);
                $rd  = \gmp_pow($n1, $pos);
            }
        } else {
            return null;
        }

        return $this->makeNumeric($rn, $rd, $s, $e);
    }

    private function foldComplex(
        BinaryOperatorNode $bin,
        NumericNode $l,
        NumericNode $r,
        int $s,
        int $e
    ): ?MathNode {
        if (!$bin instanceof PlusNode && !$bin instanceof MinusNode) {
            return null;
        }

        $lReal = ($l instanceof ComplexNode) ? $l->getReal()
               : (($l instanceof IntegerNode) ? $l->getValue() : null);
        $lImag = ($l instanceof ComplexNode) ? $l->getImag() : \gmp_init(0);
        $rReal = ($r instanceof ComplexNode) ? $r->getReal()
               : (($r instanceof IntegerNode) ? $r->getValue() : null);
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

        if (\gmp_cmp($imag, 0) === 0) {
            return new IntegerNode(\gmp_strval($real), $s, $e);
        }
        return new ComplexNode(\gmp_strval($real), \gmp_strval($imag), $s, $e);
    }

    private function applyPowerRules(MathNode $l, MathNode $r, int $s, int $e): ?MathNode
    {
        // (X^a)^b → X^(a*b)  when both exponents are integers
        if ($l instanceof PowerNode
            && $l->getRight() instanceof IntegerNode
            && $r instanceof IntegerNode
        ) {
            $newExp = \gmp_mul($l->getRight()->getValue(), $r->getValue());
            return new PowerNode(
                $l->getLeft(),
                new IntegerNode(\gmp_strval($newExp), $s, $e),
                $s, $e
            );
        }
        return null;
    }

    private function applyLikeTerms(
        BinaryOperatorNode $bin,
        MathNode $l,
        MathNode $r,
        int $s,
        int $e
    ): ?MathNode {
        [$c1, $t1] = $this->extractCoefficientAndTerm($l);
        [$c2, $t2] = $this->extractCoefficientAndTerm($r);

        if ($c1 === null || $c2 === null) {
            return null;
        }
        if (!$this->structuralEquals($t1, $t2)) {
            return null;
        }

        [$n1, $d1] = $this->toRationalPair($c1);
        [$n2, $d2] = $this->toRationalPair($c2);

        if ($bin instanceof PlusNode) {
            [$cn, $cd] = $this->rationalAdd($n1, $d1, $n2, $d2);
        } else {
            [$cn, $cd] = $this->rationalSub($n1, $d1, $n2, $d2);
        }

        if (\gmp_cmp($cn, 0) === 0) {
            return new IntegerNode('0', $s, $e);
        }

        $newCoeff = $this->makeNumeric($cn, $cd, $s, $e);

        // 1*T → T
        if ($this->isNumericOne($newCoeff) && !$this->isNumericOne($t1)) {
            return $t1;
        }
        // c*1 → c
        if ($this->isNumericOne($t1)) {
            return $newCoeff;
        }

        return new MultiplyNode($newCoeff, $t1, $s, $e);
    }

    private function applyDistribution(MathNode $l, MathNode $r, int $s, int $e): ?MathNode
    {
        // a * (b + c) → a*b + a*c
        if ($r instanceof PlusNode) {
            return new PlusNode(
                new MultiplyNode($l, $r->getLeft(),  $s, $e),
                new MultiplyNode($l, $r->getRight(), $s, $e),
                $s, $e
            );
        }
        // a * (b - c) → a*b - a*c
        if ($r instanceof MinusNode) {
            return new MinusNode(
                new MultiplyNode($l, $r->getLeft(),  $s, $e),
                new MultiplyNode($l, $r->getRight(), $s, $e),
                $s, $e
            );
        }
        // (b + c) * a → b*a + c*a
        if ($l instanceof PlusNode) {
            return new PlusNode(
                new MultiplyNode($l->getLeft(),  $r, $s, $e),
                new MultiplyNode($l->getRight(), $r, $s, $e),
                $s, $e
            );
        }
        // (b - c) * a → b*a - c*a
        if ($l instanceof MinusNode) {
            return new MinusNode(
                new MultiplyNode($l->getLeft(),  $r, $s, $e),
                new MultiplyNode($l->getRight(), $r, $s, $e),
                $s, $e
            );
        }
        return null;
    }

    // ─── Sqrt / Root ──────────────────────────────────────────────────

    private function simplifySqrt(SqrtNode $s): MathNode
    {
        $rad   = $this->simplifyNode($s->getRadicand());
        $start = $s->getStartPos();
        $end   = $s->getEndPos();

        if ($rad instanceof IntegerNode
            && \gmp_cmp($rad->getValue(), 0) >= 0
            && \gmp_perfect_square($rad->getValue())
        ) {
            $root = new IntegerNode(\gmp_strval(\gmp_sqrt($rad->getValue())), $start, $end);
            $this->notify('sqrt perfect square', $s, $root);
            return $root;
        }

        // sqrt(X^2) → X  when X ≥ 0
        if ($rad instanceof PowerNode
            && $rad->getRight() instanceof IntegerNode
            && \gmp_cmp($rad->getRight()->getValue(), 2) === 0
            && $rad->getLeft() instanceof IntegerNode
            && \gmp_cmp($rad->getLeft()->getValue(), 0) >= 0
        ) {
            return $rad->getLeft();
        }

        if ($rad !== $s->getRadicand()) {
            return new SqrtNode($rad, $start, $end);
        }
        return $s;
    }

    private function simplifyRoot(RootNode $r): MathNode
    {
        $deg   = $this->simplifyNode($r->getDegree());
        $rad   = $this->simplifyNode($r->getRadicand());
        $start = $r->getStartPos();
        $end   = $r->getEndPos();

        // root(1, X) → X
        if ($deg instanceof IntegerNode && \gmp_cmp($deg->getValue(), 1) === 0) {
            $this->notify('root degree 1', $r, $rad);
            return $rad;
        }

        if ($rad instanceof IntegerNode && $deg instanceof IntegerNode) {
            $nth = (int) \gmp_strval($deg->getValue());
            if ($nth > 1 && \gmp_cmp($rad->getValue(), 0) >= 0) {
                $rem = \gmp_rootrem($rad->getValue(), $nth);
                if (\gmp_cmp($rem[1], 0) === 0) {
                    $result = new IntegerNode(\gmp_strval($rem[0]), $start, $end);
                    $this->notify('nth root exact', $r, $result);
                    return $result;
                }
            }
        }

        if ($deg !== $r->getDegree() || $rad !== $r->getRadicand()) {
            return new RootNode($deg, $rad, $start, $end);
        }
        return $r;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STRUCTURAL EQUALITY
    // ═══════════════════════════════════════════════════════════════════

    public function structuralEquals(MathNode $a, MathNode $b): bool
    {
        if (get_class($a) !== get_class($b)) {
            return false;
        }
        if ($a instanceof IntegerNode) {
            return \gmp_cmp($a->getValue(), $b->getValue()) === 0;
        }
        if ($a instanceof RationalNode) {
            /** @var RationalNode $b */
            return \gmp_cmp($a->getValueOfNumerator(),   $b->getValueOfNumerator())   === 0
                && \gmp_cmp($a->getValueOfDenominator(), $b->getValueOfDenominator()) === 0;
        }
        if ($a instanceof ComplexNode) {
            /** @var ComplexNode $b */
            return \gmp_cmp($a->getReal(), $b->getReal()) === 0
                && \gmp_cmp($a->getImag(), $b->getImag()) === 0;
        }
        if ($a instanceof PiNode)       { return true; }
        if ($a instanceof VariableNode) {
            /** @var VariableNode $b */
            return $a->getName() === $b->getName();
        }
        if ($a instanceof UnaryNode) {
            /** @var UnaryNode $b */
            return $a->getOp() === $b->getOp()
                && $this->structuralEquals($a->getOperand(), $b->getOperand());
        }
        // Commutative nodes: Plus, Multiply
        if ($a instanceof PlusNode || $a instanceof MultiplyNode) {
            /** @var BinaryOperatorNode $a @var BinaryOperatorNode $b */
            return ($this->structuralEquals($a->getLeft(), $b->getLeft())
                    && $this->structuralEquals($a->getRight(), $b->getRight()))
                || ($this->structuralEquals($a->getLeft(), $b->getRight())
                    && $this->structuralEquals($a->getRight(), $b->getLeft()));
        }
        if ($a instanceof BinaryOperatorNode) {
            /** @var BinaryOperatorNode $b */
            return $this->structuralEquals($a->getLeft(), $b->getLeft())
                && $this->structuralEquals($a->getRight(), $b->getRight());
        }
        if ($a instanceof SqrtNode) {
            /** @var SqrtNode $b */
            return $this->structuralEquals($a->getRadicand(), $b->getRadicand());
        }
        if ($a instanceof RootNode) {
            /** @var RootNode $b */
            return $this->structuralEquals($a->getDegree(),   $b->getDegree())
                && $this->structuralEquals($a->getRadicand(), $b->getRadicand());
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════════

    public function isNumericZero(MathNode $n): bool
    {
        if ($n instanceof IntegerNode)  { return \gmp_cmp($n->getValue(), 0) === 0; }
        if ($n instanceof RationalNode) { return \gmp_cmp($n->getValueOfNumerator(), 0) === 0; }
        if ($n instanceof ComplexNode)  {
            return \gmp_cmp($n->getReal(), 0) === 0 && \gmp_cmp($n->getImag(), 0) === 0;
        }
        return false;
    }

    public function isNumericOne(MathNode $n): bool
    {
        if ($n instanceof IntegerNode)  { return \gmp_cmp($n->getValue(), 1) === 0; }
        if ($n instanceof RationalNode) {
            return \gmp_cmp($n->getValueOfNumerator(), 0) !== 0
                && \gmp_cmp($n->getValueOfNumerator(), $n->getValueOfDenominator()) === 0;
        }
        if ($n instanceof ComplexNode)  {
            return \gmp_cmp($n->getReal(), 1) === 0 && \gmp_cmp($n->getImag(), 0) === 0;
        }
        return false;
    }

    /**
     * Extract (coefficient, term) from a node for like-term detection.
     * Returns [null, $n] when the pattern is not recognised.
     *
     * @return array{0: NumericNode|null, 1: MathNode}
     */
    private function extractCoefficientAndTerm(MathNode $n): array
    {
        if ($n instanceof NumericNode) {
            return [$n, new IntegerNode('1', -1, -1)];
        }
        if ($n instanceof VariableNode || $n instanceof PiNode) {
            return [new IntegerNode('1', -1, -1), $n];
        }
        if ($n instanceof UnaryNode && $n->getOp() === '-') {
            $inner = $n->getOperand();
            if ($inner instanceof NumericNode) {
                return [$inner, new IntegerNode('1', -1, -1)];
            }
            return [new IntegerNode('-1', -1, -1), $inner];
        }
        if ($n instanceof MultiplyNode) {
            $l = $n->getLeft();
            $r = $n->getRight();
            if ($l instanceof NumericNode) return [$l, $r];
            if ($r instanceof NumericNode) return [$r, $l];
            return [null, $n];
        }
        if ($n instanceof PowerNode || $n instanceof SqrtNode || $n instanceof RootNode) {
            return [new IntegerNode('1', -1, -1), $n];
        }
        return [null, $n];
    }

    /** @return array{\GMP, \GMP} */
    public function toRationalPair(NumericNode $n): array
    {
        if ($n instanceof IntegerNode)  { return [$n->getValue(), \gmp_init(1)]; }
        if ($n instanceof RationalNode) { return [$n->getValueOfNumerator(), $n->getValueOfDenominator()]; }
        throw new SimplifyException('Cannot convert node to rational: ' . get_class($n));
    }

    public function makeNumeric(\GMP $num, \GMP $den, int $start, int $end): NumericNode
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

    private function makeBinaryOp(
        string $class,
        MathNode $l,
        MathNode $r,
        int $s,
        int $e
    ): BinaryOperatorNode {
        return new $class($l, $r, $s, $e);
    }

    // ─── Rational arithmetic ──────────────────────────────────────────

    private function rationalAdd(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        return [\gmp_add(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1)), \gmp_mul($d1, $d2)];
    }

    private function rationalSub(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        return [\gmp_sub(\gmp_mul($n1, $d2), \gmp_mul($n2, $d1)), \gmp_mul($d1, $d2)];
    }

    private function rationalMul(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        return [\gmp_mul($n1, $n2), \gmp_mul($d1, $d2)];
    }

    private function rationalDiv(\GMP $n1, \GMP $d1, \GMP $n2, \GMP $d2): array
    {
        if (\gmp_cmp($n2, 0) === 0) {
            throw new SimplifyException('Division by zero.');
        }
        return [\gmp_mul($n1, $d2), \gmp_mul($d1, $n2)];
    }

    // ─── Observer ────────────────────────────────────────────────────

    private function notify(string $rule, MathNode $before, MathNode $after): void
    {
        if ($this->observer !== null) {
            $this->observer->onRuleApplied($rule, $before, $after);
        }
    }
}
