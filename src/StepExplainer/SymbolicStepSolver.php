<?php
namespace Sobhanmohammadi\CAS\StepExplainer;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode,
    VariableNode, PiNode, EquationNode,
    TrigFunctionNode, Atan2Node
};
use Sobhanmohammadi\CAS\Parser\Lexer;
use Sobhanmohammadi\CAS\Parser\Parser;
use Sobhanmohammadi\CAS\Services\Simplifier;
use Sobhanmohammadi\CAS\Services\SymbolTable;

class SymbolicStepSolver
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

    public function solve(string $equation, string $unknown): array
    {
        $this->recorder = new StepRecorder();

        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        $eqNode = $parser->parseEquation();

        $this->recorder->record(StepExplainer::equationStart($equation));
        $this->recorder->record(StepExplainer::classificationEquation($unknown));

        try {
            $leftNode  = $this->simplifier->simplifyFully($eqNode->getLeft());
            $rightNode = $this->simplifier->simplifyFully($eqNode->getRight());

            [$a1, $b1] = $this->extractLinearCoefficient($leftNode, $unknown);
            [$a2, $b2] = $this->extractLinearCoefficient($rightNode, $unknown);

            $a1 = $this->simplifier->simplifyFully($a1);
            $b1 = $this->simplifier->simplifyFully($b1);
            $a2 = $this->simplifier->simplifyFully($a2);
            $b2 = $this->simplifier->simplifyFully($b2);

            $this->recorder->record(StepExplainer::solverSimplify(
                $unknown,
                $b1->toMathString(),
                $a1->toMathString(),
                $b2->toMathString(),
                $a2->toMathString(),
                $leftNode->toMathString() . ' = ' . $rightNode->toMathString()
            ));

            $aExpr = $this->simplifier->simplifyFully(new MinusNode($a1, $a2, 0, 0));
            $bExpr = $this->simplifier->simplifyFully(new MinusNode($b1, $b2, 0, 0));

            $negBExpr = $this->simplifier->simplifyFully(new UnaryNode('-', $bExpr, 0, 0));

            $this->recorder->record(StepExplainer::solverCollect(
                $unknown,
                $a1->toMathString(),
                $a2->toMathString(),
                $aExpr->toMathString(),
                $b1->toMathString(),
                $b2->toMathString(),
                $negBExpr->toMathString(),
                $aExpr->toMathString() . ' * ' . $unknown . ' = ' . $negBExpr->toMathString()
            ));

            if ($this->isZeroNode($aExpr)) {
                $isIdentity = $this->isZeroNode($bExpr);
                $constFmt = $bExpr->toMathString();
                $this->recorder->record(StepExplainer::solverDegenerate($unknown, $isIdentity, $constFmt));
                if ($isIdentity) {
                    throw new \RuntimeException('Infinite solutions (identity).');
                }
                throw new \RuntimeException('No solution (contradiction).');
            }

            $solution = $this->simplifier->simplifyFully(new DivideNode($negBExpr, $aExpr, 0, 0));

            if ($this->isOneNode($aExpr)) {
                $this->recorder->record(StepExplainer::solverDivideIsolated($unknown, $solution->toMathString()));
            } else {
                $this->recorder->record(StepExplainer::solverDivide(
                    $unknown,
                    $aExpr->toMathString(),
                    $negBExpr->toMathString(),
                    $solution->toMathString()
                ));
            }

            $verifLeft  = $this->simplifier->simplifyFully($this->substitute($eqNode->getLeft(), $unknown, $solution));
            $verifRight = $this->simplifier->simplifyFully($this->substitute($eqNode->getRight(), $unknown, $solution));
            $lhsStr = $verifLeft->toMathString();
            $rhsStr = $verifRight->toMathString();
            $ok = ($lhsStr === $rhsStr);
            $this->recorder->record(StepExplainer::solverVerify(
                $unknown,
                $solution->toMathString(),
                $equation,
                $lhsStr,
                $rhsStr,
                '',
                $ok
            ));

            $this->recorder->record(StepExplainer::finalEquationResult($unknown, $solution->toMathString()));

            return $this->recorder->getSteps();
        } catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), 'Nonlinear') === false) {
                throw $e;
            }
            $this->recorder->record(StepExplainer::solverNonLinear($unknown, '', $e->getMessage()));
            throw $e;
        }
    }

    private function substitute(MathNode $expr, string $varName, MathNode $value): MathNode
    {
        if ($expr instanceof VariableNode && $expr->getName() === $varName) {
            return $value;
        }
        if ($expr instanceof PlusNode || $expr instanceof MinusNode ||
            $expr instanceof MultiplyNode || $expr instanceof DivideNode || $expr instanceof PowerNode) {
            $left  = $this->substitute($expr->getLeft(), $varName, $value);
            $right = $this->substitute($expr->getRight(), $varName, $value);
            $class = get_class($expr);
            return new $class($left, $right, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof UnaryNode) {
            $operand = $this->substitute($expr->getOperand(), $varName, $value);
            return new UnaryNode($expr->getOp(), $operand, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof SqrtNode) {
            $rad = $this->substitute($expr->getRadicand(), $varName, $value);
            return new SqrtNode($rad, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof RootNode) {
            $deg = $this->substitute($expr->getDegree(), $varName, $value);
            $rad = $this->substitute($expr->getRadicand(), $varName, $value);
            return new RootNode($deg, $rad, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof Atan2Node) {
            $y = $this->substitute($expr->getY(), $varName, $value);
            $x = $this->substitute($expr->getX(), $varName, $value);
            return new Atan2Node($y, $x, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof TrigFunctionNode) {
            $class = get_class($expr);
            $arg = $this->substitute($expr->getArgument(), $varName, $value);
            return new $class($arg, $expr->getStartPos(), $expr->getEndPos());
        }
        return $expr;
    }

    private function isZeroNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && gmp_cmp($node->getValue(), 0) === 0;
    }

    private function isOneNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && gmp_cmp($node->getValue(), 1) === 0;
    }

    private function extractLinearCoefficient(MathNode $expr, string $unknown): array
    {
        if ($expr instanceof VariableNode && $expr->getName() === $unknown) {
            return [new IntegerNode('1', 0, 0), new IntegerNode('0', 0, 0)];
        }
        if ($expr instanceof PiNode || $expr instanceof IntegerNode || $expr instanceof RationalNode) {
            return [new IntegerNode('0', 0, 0), $expr];
        }
        if ($expr instanceof UnaryNode) {
            if ($expr->getOp() !== '-') {
                throw new \RuntimeException('Unsupported unary operator in linear equation.');
            }
            [$c, $k] = $this->extractLinearCoefficient($expr->getOperand(), $unknown);
            return [
                new UnaryNode('-', $c, 0, 0),
                new UnaryNode('-', $k, 0, 0)
            ];
        }
        if ($expr instanceof PlusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new PlusNode($c1, $c2, 0, 0),
                new PlusNode($k1, $k2, 0, 0)
            ];
        }
        if ($expr instanceof MinusNode) {
            [$c1, $k1] = $this->extractLinearCoefficient($expr->getLeft(), $unknown);
            [$c2, $k2] = $this->extractLinearCoefficient($expr->getRight(), $unknown);
            return [
                new MinusNode($c1, $c2, 0, 0),
                new MinusNode($k1, $k2, 0, 0)
            ];
        }
        if ($expr instanceof MultiplyNode) {
            return $this->extractFromProduct($expr, $unknown);
        }
        if ($expr instanceof DivideNode) {
            $num = $expr->getLeft();
            $den = $expr->getRight();
            if ($this->containsVariable($den, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in denominator.');
            }
            [$c, $k] = $this->extractLinearCoefficient($num, $unknown);
            return [
                new DivideNode($c, $den, 0, 0),
                new DivideNode($k, $den, 0, 0)
            ];
        }
        if ($expr instanceof PowerNode) {
            $base = $expr->getLeft();
            $exp  = $expr->getRight();
            if ($this->containsVariable($exp, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable in exponent.');
            }
            if ($this->containsVariable($base, $unknown)) {
                if ($exp instanceof IntegerNode && gmp_cmp($exp->getValue(), 1) === 0) {
                    return $this->extractLinearCoefficient($base, $unknown);
                }
                throw new \RuntimeException('Nonlinear equation: variable raised to power > 1.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }
        if ($expr instanceof SqrtNode || $expr instanceof RootNode) {
            if ($this->containsVariable($expr, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable inside radical.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }
        if ($expr instanceof TrigFunctionNode || $expr instanceof Atan2Node) {
            if ($this->containsVariable($expr, $unknown)) {
                throw new \RuntimeException('Nonlinear equation: variable inside trigonometric function.');
            }
            return [new IntegerNode('0', 0, 0), $expr];
        }
        if ($expr instanceof VariableNode) {
            return [new IntegerNode('0', 0, 0), $expr];
        }
        throw new \RuntimeException('Unsupported node type in linear equation: ' . get_class($expr));
    }

    private function extractFromProduct(MultiplyNode $mul, string $unknown): array
    {
        $left  = $mul->getLeft();
        $right = $mul->getRight();
        $leftHas  = $this->containsVariable($left, $unknown);
        $rightHas = $this->containsVariable($right, $unknown);
        if ($leftHas && $rightHas) {
            throw new \RuntimeException('Nonlinear equation: variable multiplied by itself.');
        }
        if (!$leftHas && !$rightHas) {
            return [new IntegerNode('0', 0, 0), $mul];
        }
        $varFactor   = $leftHas ? $left : $right;
        $constFactor = $leftHas ? $right : $left;
        [$c, $k] = $this->extractLinearCoefficient($varFactor, $unknown);
        return [
            new MultiplyNode($constFactor, $c, 0, 0),
            new MultiplyNode($constFactor, $k, 0, 0)
        ];
    }

    private function containsVariable(MathNode $node, string $varName): bool
    {
        if ($node instanceof VariableNode) {
            return $node->getName() === $varName;
        }
        if ($node instanceof PlusNode || $node instanceof MinusNode ||
            $node instanceof MultiplyNode || $node instanceof DivideNode || $node instanceof PowerNode) {
            return $this->containsVariable($node->getLeft(), $varName) ||
                   $this->containsVariable($node->getRight(), $varName);
        }
        if ($node instanceof UnaryNode) {
            return $this->containsVariable($node->getOperand(), $varName);
        }
        if ($node instanceof SqrtNode) {
            return $this->containsVariable($node->getRadicand(), $varName);
        }
        if ($node instanceof RootNode) {
            return $this->containsVariable($node->getDegree(), $varName) ||
                   $this->containsVariable($node->getRadicand(), $varName);
        }
        if ($node instanceof TrigFunctionNode) {
            return $this->containsVariable($node->getArgument(), $varName);
        }
        if ($node instanceof Atan2Node) {
            return $this->containsVariable($node->getY(), $varName) ||
                   $this->containsVariable($node->getX(), $varName);
        }
        return false;
    }
}