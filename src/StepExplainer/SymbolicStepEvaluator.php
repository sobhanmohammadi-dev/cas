<?php
namespace CAS\StepExplainer;

use CAS\Nodes\{BinaryOperatorNode,
    MathNode,
    NumericNode,
    IntegerNode,
    RationalNode,
    PlusNode,
    MinusNode,
    MultiplyNode,
    DivideNode,
    PowerNode,
    UnaryNode,
    SqrtNode,
    RootNode,
    VariableNode,
    PiNode};
use CAS\Services\SymbolTable;
use CAS\Services\Simplifier;

class SymbolicStepEvaluator
{
    private SymbolTable $symbolTable;
    private Simplifier $simplifier;
    private StepRecorder $recorder;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->simplifier = new Simplifier($symbolTable);
        $this->recorder = new StepRecorder();
    }

    public function evaluate(MathNode $node): MathNode
    {
        $this->recorder = new StepRecorder();
        return $this->evaluateNode($node);
    }

    public function getSteps(): array
    {
        return $this->recorder->getSteps();
    }

    private function evaluateNode(MathNode $node): MathNode
    {
        if ($node instanceof NumericNode || $node instanceof PiNode) {
            return $node;
        }

        if ($node instanceof VariableNode) {
            $name = $node->getName();
            $bound = $this->symbolTable->lookup($name);
            if ($bound !== null) {
                $boundStr = $bound instanceof NumericNode ? $bound->toMathString() : $bound->__toString();
                $this->recorder->record(StepExplainer::variableSubstitution($name, $boundStr));
                return $bound;
            }
            return $node;
        }

        if ($node instanceof UnaryNode && $node->getOp() === '-') {
            $inner = $this->evaluateNode($node->getOperand());
            if ($inner instanceof UnaryNode && $inner->getOp() === '-') {
                $this->recorder->record(StepExplainer::algebraicRuleApplied('double negation', $node->__toString(), $inner->getOperand()->__toString()));
                return $inner->getOperand();
            }
            if ($inner instanceof IntegerNode) {
                $neg = new IntegerNode(\gmp_strval(\gmp_neg($inner->getValue())), $node->getStartPos(), $node->getEndPos());
                $this->recorder->record(StepExplainer::unaryNegation($inner->toMathString(), $neg->toMathString()));
                return $neg;
            }
            if ($inner instanceof RationalNode) {
                $neg = new RationalNode(\gmp_strval(\gmp_neg($inner->getValueOfNumerator())), \gmp_strval($inner->getValueOfDenominator()), $node->getStartPos(), $node->getEndPos());
                $this->recorder->record(StepExplainer::unaryNegation($inner->toMathString(), $neg->toMathString()));
                return $neg;
            }
            if ($inner !== $node->getOperand()) {
                return new UnaryNode('-', $inner, $node->getStartPos(), $node->getEndPos());
            }
            return $node;
        }

        if ($node instanceof BinaryOperatorNode) {
            return $this->evaluateBinaryOp($node);
        }

        if ($node instanceof SqrtNode) {
            $rad = $this->evaluateNode($node->getRadicand());
            if ($rad instanceof IntegerNode && \gmp_cmp($rad->getValue(), 0) >= 0 && \gmp_perfect_square($rad->getValue())) {
                $root = new IntegerNode(\gmp_strval(\gmp_sqrt($rad->getValue())), $node->getStartPos(), $node->getEndPos());
                $this->recorder->record(StepExplainer::sqrtOperation($rad->toMathString(), $root->toMathString(), true, 0, $root->toMathString()));
                return $root;
            }
            if ($rad !== $node->getRadicand()) {
                return new SqrtNode($rad, $node->getStartPos(), $node->getEndPos());
            }
            return $node;
        }

        if ($node instanceof RootNode) {
            $deg = $this->evaluateNode($node->getDegree());
            $rad = $this->evaluateNode($node->getRadicand());
            if ($deg instanceof IntegerNode && \gmp_cmp($deg->getValue(), 1) === 0) {
                $this->recorder->record(StepExplainer::algebraicRuleApplied('root of degree 1', $node->__toString(), $rad->__toString()));
                return $rad;
            }
            if ($rad instanceof IntegerNode && $deg instanceof IntegerNode) {
                $n = (int)\gmp_strval($deg->getValue());
                if ($n > 1 && \gmp_cmp($rad->getValue(), 0) >= 0) {
                    $rem = \gmp_rootrem($rad->getValue(), $n);
                    if (\gmp_cmp($rem[1], 0) === 0) {
                        $root = new IntegerNode(\gmp_strval($rem[0]), $node->getStartPos(), $node->getEndPos());
                        $this->recorder->record(StepExplainer::radicalOperation($deg->toMathString(), $rad->toMathString(), $root->toMathString(), $n . 'th'));
                        return $root;
                    }
                }
            }
            if ($deg !== $node->getDegree() || $rad !== $node->getRadicand()) {
                return new RootNode($deg, $rad, $node->getStartPos(), $node->getEndPos());
            }
            return $node;
        }

        return $node;
    }

    private function evaluateBinaryOp(BinaryOperatorNode $node): MathNode
    {
        $left  = $this->evaluateNode($node->getLeft());
        $right = $this->evaluateNode($node->getRight());
        $start = $node->getStartPos();
        $end   = $node->getEndPos();

        $result = $this->applyIdentity(get_class($node), $left, $right, $start, $end);
        if ($result !== null) {
            if ($result !== $node) {
                $this->recorder->record(StepExplainer::algebraicRuleApplied('identity/annihilation', $node->__toString(), $result->__toString()));
            }
            return $result;
        }

        if ($left instanceof NumericNode && $right instanceof NumericNode) {
            $folded = $this->constantFold(get_class($node), $left, $right, $start, $end);
            if ($folded !== null) {
                $this->recorder->record(StepExplainer::constantChain($node->__toString(), $folded->toMathString()));
                return $folded;
            }
        }

        if ($node instanceof PlusNode || $node instanceof MinusNode) {
            $combined = $this->combineLikeTerms(get_class($node), $left, $right, $start, $end);
            if ($combined !== null) {
                $this->recorder->record(StepExplainer::algebraicRuleApplied('combine like terms', $node->__toString(), $combined->__toString()));
                return $combined;
            }
        }

        if ($node instanceof MultiplyNode) {
            $dist = $this->distribute($left, $right, $start, $end);
            if ($dist !== null) {
                $this->recorder->record(StepExplainer::algebraicRuleApplied('distribution', $node->__toString(), $dist->__toString()));
                return $dist;
            }
        }

        if ($left !== $node->getLeft() || $right !== $node->getRight()) {
            return $this->makeNode(get_class($node), $left, $right, $start, $end);
        }
        return $node;
    }

    private function applyIdentity(string $class, MathNode $l, MathNode $r, int $s, int $e): ?MathNode
    {
        if ($class === PlusNode::class) {
            if ($this->isZero($l)) return $r;
            if ($this->isZero($r)) return $l;
        }
        if ($class === MinusNode::class) {
            if ($this->isZero($r)) return $l;
            if ($this->isZero($l)) return new UnaryNode('-', $r, $s, $e);
        }
        if ($class === MultiplyNode::class) {
            if ($this->isZero($l) || $this->isZero($r)) return new IntegerNode('0', $s, $e);
            if ($this->isOne($l)) return $r;
            if ($this->isOne($r)) return $l;
        }
        if ($class === DivideNode::class) {
            if ($this->isZero($l)) return new IntegerNode('0', $s, $e);
            if ($this->isOne($r)) return $l;
        }
        if ($class === PowerNode::class) {
            if ($this->isZero($r)) return new IntegerNode('1', $s, $e);
            if ($this->isOne($r)) return $l;
            if ($this->isOne($l)) return new IntegerNode('1', $s, $e);
            if ($this->isZero($l) && !$this->isZero($r)) return new IntegerNode('0', $s, $e);
        }
        return null;
    }

    private function constantFold(string $class, NumericNode $l, NumericNode $r, int $s, int $e): ?MathNode
    {
        $a = $this->toRational($l);
        $b = $this->toRational($r);
        $num = $den = null;
        switch ($class) {
            case PlusNode::class:
                $num = \gmp_add(\gmp_mul($a[0], $b[1]), \gmp_mul($b[0], $a[1]));
                $den = \gmp_mul($a[1], $b[1]);
                break;
            case MinusNode::class:
                $num = \gmp_sub(\gmp_mul($a[0], $b[1]), \gmp_mul($b[0], $a[1]));
                $den = \gmp_mul($a[1], $b[1]);
                break;
            case MultiplyNode::class:
                $num = \gmp_mul($a[0], $b[0]);
                $den = \gmp_mul($a[1], $b[1]);
                break;
            case DivideNode::class:
                if (\gmp_cmp($b[0], 0) === 0) throw new \RuntimeException('Division by zero');
                $num = \gmp_mul($a[0], $b[1]);
                $den = \gmp_mul($a[1], $b[0]);
                break;
            case PowerNode::class:
                if (!$r instanceof IntegerNode) return null;
                $exp = (int)\gmp_strval($r->getValue());
                if ($exp >= 0) {
                    $num = \gmp_pow($a[0], $exp);
                    $den = \gmp_pow($a[1], $exp);
                } else {
                    $pos = -$exp;
                    $num = \gmp_pow($a[1], $pos);
                    $den = \gmp_pow($a[0], $pos);
                }
                break;
            default: return null;
        }
        return $this->makeReduced($num, $den, $s, $e);
    }

    private function combineLikeTerms(string $class, MathNode $l, MathNode $r, int $s, int $e): ?MathNode
    {
        [$c1, $t1] = $this->extractCoeffTerm($l);
        [$c2, $t2] = $this->extractCoeffTerm($r);
        if ($c1 === null || $c2 === null) return null;
        if (!$this->treeEquals($t1, $t2)) return null;

        $coeff = $class === PlusNode::class
            ? new PlusNode($c1, $c2, $s, $e)
            : new MinusNode($c1, $c2, $s, $e);
        $simpCoeff = $this->evaluateNode($coeff);
        if ($this->isZero($simpCoeff)) return new IntegerNode('0', $s, $e);
        if ($this->isOne($simpCoeff)) return $t1;
        return new MultiplyNode($simpCoeff, $t1, $s, $e);
    }

    private function distribute(MathNode $l, MathNode $r, int $s, int $e): ?MathNode
    {
        if ($l instanceof PlusNode) {
            return new PlusNode(
                new MultiplyNode($l->getLeft(), $r, $s, $e),
                new MultiplyNode($l->getRight(), $r, $s, $e),
                $s, $e
            );
        }
        if ($l instanceof MinusNode) {
            return new MinusNode(
                new MultiplyNode($l->getLeft(), $r, $s, $e),
                new MultiplyNode($l->getRight(), $r, $s, $e),
                $s, $e
            );
        }
        if ($r instanceof PlusNode) {
            return new PlusNode(
                new MultiplyNode($l, $r->getLeft(), $s, $e),
                new MultiplyNode($l, $r->getRight(), $s, $e),
                $s, $e
            );
        }
        if ($r instanceof MinusNode) {
            return new MinusNode(
                new MultiplyNode($l, $r->getLeft(), $s, $e),
                new MultiplyNode($l, $r->getRight(), $s, $e),
                $s, $e
            );
        }
        return null;
    }

    private function isZero(MathNode $n): bool
    {
        return $n instanceof IntegerNode && \gmp_cmp($n->getValue(), 0) === 0;
    }
    private function isOne(MathNode $n): bool
    {
        return $n instanceof IntegerNode && \gmp_cmp($n->getValue(), 1) === 0;
    }
    private function toRational(NumericNode $n): array
    {
        if ($n instanceof IntegerNode) return [$n->getValue(), \gmp_init(1)];
        if ($n instanceof RationalNode) return [$n->getValueOfNumerator(), $n->getValueOfDenominator()];
        throw new \RuntimeException('Unsupported numeric type');
    }
    private function makeReduced($num, $den, int $s, int $e): MathNode
    {
        $gcd = \gmp_gcd($num, $den);
        $num = \gmp_div_q($num, $gcd);
        $den = \gmp_div_q($den, $gcd);
        if (\gmp_sign($den) === -1) { $num = \gmp_neg($num); $den = \gmp_abs($den); }
        if (\gmp_cmp($den, 1) === 0) return new IntegerNode(\gmp_strval($num), $s, $e);
        return new RationalNode(\gmp_strval($num), \gmp_strval($den), $s, $e);
    }
    private function extractCoeffTerm(MathNode $n): array
    {
        if ($n instanceof NumericNode) return [$n, new IntegerNode('1', 0, 0)];
        if ($n instanceof VariableNode || $n instanceof PiNode) return [new IntegerNode('1', 0, 0), $n];
        if ($n instanceof UnaryNode && $n->getOp() === '-') {
            $inner = $n->getOperand();
            if ($inner instanceof NumericNode) return [$n, new IntegerNode('1', 0, 0)];
            return [new IntegerNode('-1', 0, 0), $inner];
        }
        if ($n instanceof MultiplyNode) {
            $l = $n->getLeft(); $r = $n->getRight();
            if ($l instanceof NumericNode) return [$l, $r];
            if ($r instanceof NumericNode) return [$r, $l];
            return [null, $n];
        }
        return [new IntegerNode('1', 0, 0), $n];
    }
    private function treeEquals(MathNode $a, MathNode $b): bool
    {
        return (new Simplifier($this->symbolTable))->simplifyFully($a)->__toString()
            === (new Simplifier($this->symbolTable))->simplifyFully($b)->__toString();
    }
    private function makeNode(string $class, MathNode $l, MathNode $r, int $s, int $e): BinaryOperatorNode
    {
        return new $class($l, $r, $s, $e);
    }
}