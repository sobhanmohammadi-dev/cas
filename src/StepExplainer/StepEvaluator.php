<?php
namespace CAS\StepExplainer;

use CAS\Nodes\{
    MathNode, IntegerNode, RationalNode, ComplexNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode, PiNode, VariableNode, BinaryOperatorNode
};
use CAS\Parser\Lexer;
use CAS\Parser\Parser;
use CAS\Services\SymbolTable;

class StepEvaluator
{
    private SymbolTable $symbolTable;
    private int $scale;
    private StepRecorder $recorder;

    public function __construct(SymbolTable $symbolTable, int $scale = 10)
    {
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('BCMath extension is required for StepEvaluator.');
        }
        $this->symbolTable = $symbolTable;
        $this->scale = $scale;
        $this->recorder = new StepRecorder();
    }

    public function evaluateExpression(string $expression): array
    {
        $this->recorder = new StepRecorder();

        $lexer  = new Lexer($expression);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $expression);
        $ast    = $parser->parse();

        $this->recorder->record(StepExplainer::expressionStart($expression));

        $result = $this->evaluateNode($ast);

        $this->recorder->record(StepExplainer::finalExpressionResult($result));

        return $this->recorder->getSteps();
    }

    private function evaluateNode(MathNode $node): string
    {
        if ($node instanceof IntegerNode) {
            return gmp_strval($node->getValue());
        }

        if ($node instanceof RationalNode) {
            $num = gmp_strval($node->getValueOfNumerator());
            $den = gmp_strval($node->getValueOfDenominator());
            return bcdiv($num, $den, $this->scale);
        }

        if ($node instanceof ComplexNode) {
            throw new \RuntimeException('Complex numbers not supported in step evaluator.');
        }

        if ($node instanceof PiNode) {
            $pi = self::piApproximation($this->scale);
            $this->recorder->record(StepExplainer::piSubstitution($pi));
            return $pi;
        }

        if ($node instanceof VariableNode) {
            $name = $node->getName();
            $value = $this->symbolTable->lookup($name);
            if ($value === null) {
                throw new \RuntimeException("Undefined variable '{$name}'.");
            }
            $valStr = $this->evaluateNode($value);
            $this->recorder->record(StepExplainer::variableSubstitution($name, $valStr));
            return $valStr;
        }

        if ($node instanceof UnaryNode) {
            $op = $node->getOp();
            if ($op !== '-') {
                throw new \RuntimeException('Only unary minus is supported.');
            }
            $operandStr = $this->evaluateNode($node->getOperand());
            $result = bcmul('-1', $operandStr, $this->scale);
            $this->recorder->record(StepExplainer::unaryNegation($operandStr, $result));
            return $result;
        }

        if ($node instanceof BinaryOperatorNode) {
            return $this->evaluateBinaryOp($node);
        }

        if ($node instanceof SqrtNode) {
            $radStr = $this->evaluateNode($node->getRadicand());
            if (bccomp($radStr, '0', $this->scale) < 0) {
                $this->recorder->record(StepExplainer::errorImaginarySqrt($radStr));
                throw new \RuntimeException('Square root of negative number.');
            }
            $result = bcsqrt($radStr, $this->scale);
            $perfect = $this->isPerfectSquareInteger($node->getRadicand());
            $this->recorder->record(StepExplainer::sqrtOperation($radStr, $result, $perfect, $this->scale, $result));
            return $result;
        }

        if ($node instanceof RootNode) {
            $degStr = $this->evaluateNode($node->getDegree());
            $radStr = $this->evaluateNode($node->getRadicand());
            $result = bcpow($radStr, bcdiv('1', $degStr, $this->scale), $this->scale);
            $this->recorder->record(StepExplainer::radicalOperation($degStr, $radStr, $result, $this->suffixForRoot((int)$degStr)));
            return $result;
        }

        throw new \RuntimeException('Unsupported node type: ' . get_class($node));
    }

    private function evaluateBinaryOp(BinaryOperatorNode $node): string
    {
        if ($this->isConstantSubtree($node)) {
            $result = $this->computeConstantValue($node);
            $exprStr = $node->__toString();
            $this->recorder->record(StepExplainer::constantChain($exprStr, $result));
            return $result;
        }

        $leftStr = $this->evaluateNode($node->getLeft());
        $rightStr = $this->evaluateNode($node->getRight());

        if ($node instanceof PlusNode) {
            $result = bcadd($leftStr, $rightStr, $this->scale);
            $rIsNeg = bccomp($rightStr, '0', $this->scale) < 0;
            $this->recorder->record(StepExplainer::addition($leftStr, $rightStr, $result, $rIsNeg));
            return $result;
        }

        if ($node instanceof MinusNode) {
            $result = bcsub($leftStr, $rightStr, $this->scale);
            $rIsNeg = bccomp($rightStr, '0', $this->scale) < 0;
            $this->recorder->record(StepExplainer::subtraction($leftStr, $rightStr, $result, $rIsNeg));
            return $result;
        }

        if ($node instanceof MultiplyNode) {
            $result = bcmul($leftStr, $rightStr, $this->scale);
            $this->recorder->record(StepExplainer::multiplication($leftStr, $rightStr, $result, false, ''));
            return $result;
        }

        if ($node instanceof DivideNode) {
            if (bccomp($rightStr, '0', $this->scale) === 0) {
                $this->recorder->record(StepExplainer::errorDivisionByZero());
                throw new \RuntimeException('Division by zero.');
            }
            $result = bcdiv($leftStr, $rightStr, $this->scale);
            $this->recorder->record(StepExplainer::division($leftStr, $rightStr, $result, ''));
            return $result;
        }

        if ($node instanceof PowerNode) {
            $result = bcpow($leftStr, $rightStr, $this->scale);
            $type = StepExplainer::powTypeDescription($leftStr, $rightStr, $result, (float)$rightStr);
            $this->recorder->record(StepExplainer::exponentiation($leftStr, $rightStr, $result, $type['en'], $type['fa']));
            return $result;
        }

        throw new \RuntimeException('Unsupported binary operator: ' . get_class($node));
    }

    private function isConstantSubtree(MathNode $node): bool
    {
        if ($node instanceof IntegerNode || $node instanceof RationalNode) {
            return true;
        }
        if ($node instanceof PiNode || $node instanceof VariableNode || $node instanceof ComplexNode) {
            return false;
        }
        if ($node instanceof UnaryNode) {
            return $this->isConstantSubtree($node->getOperand());
        }
        if ($node instanceof BinaryOperatorNode) {
            return $this->isConstantSubtree($node->getLeft()) && $this->isConstantSubtree($node->getRight());
        }
        if ($node instanceof SqrtNode) {
            return $this->isConstantSubtree($node->getRadicand());
        }
        if ($node instanceof RootNode) {
            return $this->isConstantSubtree($node->getDegree()) && $this->isConstantSubtree($node->getRadicand());
        }
        return false;
    }

    private function computeConstantValue(MathNode $node): string
    {
        if ($node instanceof IntegerNode) {
            return gmp_strval($node->getValue());
        }
        if ($node instanceof RationalNode) {
            $num = gmp_strval($node->getValueOfNumerator());
            $den = gmp_strval($node->getValueOfDenominator());
            return bcdiv($num, $den, $this->scale);
        }
        if ($node instanceof UnaryNode && $node->getOp() === '-') {
            $inner = $this->computeConstantValue($node->getOperand());
            return bcmul('-1', $inner, $this->scale);
        }
        if ($node instanceof PlusNode) {
            $l = $this->computeConstantValue($node->getLeft());
            $r = $this->computeConstantValue($node->getRight());
            return bcadd($l, $r, $this->scale);
        }
        if ($node instanceof MinusNode) {
            $l = $this->computeConstantValue($node->getLeft());
            $r = $this->computeConstantValue($node->getRight());
            return bcsub($l, $r, $this->scale);
        }
        if ($node instanceof MultiplyNode) {
            $l = $this->computeConstantValue($node->getLeft());
            $r = $this->computeConstantValue($node->getRight());
            return bcmul($l, $r, $this->scale);
        }
        if ($node instanceof DivideNode) {
            $l = $this->computeConstantValue($node->getLeft());
            $r = $this->computeConstantValue($node->getRight());
            if (bccomp($r, '0', $this->scale) === 0) {
                throw new \RuntimeException('Division by zero in constant.');
            }
            return bcdiv($l, $r, $this->scale);
        }
        if ($node instanceof PowerNode) {
            $l = $this->computeConstantValue($node->getLeft());
            $r = $this->computeConstantValue($node->getRight());
            return bcpow($l, $r, $this->scale);
        }
        if ($node instanceof SqrtNode) {
            $rad = $this->computeConstantValue($node->getRadicand());
            if (bccomp($rad, '0', $this->scale) < 0) {
                throw new \RuntimeException('Square root of negative constant.');
            }
            return bcsqrt($rad, $this->scale);
        }
        if ($node instanceof RootNode) {
            $deg = $this->computeConstantValue($node->getDegree());
            $rad = $this->computeConstantValue($node->getRadicand());
            return bcpow($rad, bcdiv('1', $deg, $this->scale), $this->scale);
        }
        throw new \RuntimeException('Unsupported constant node: ' . get_class($node));
    }

    private function isPerfectSquareInteger(MathNode $node): bool
    {
        if ($node instanceof IntegerNode) {
            $val = $node->getValue();
            return gmp_perfect_square($val);
        }
        return false;
    }

    private static function piApproximation(int $scale): string
    {
        $pi = '3.1415926535897932384626433832795028841971693993751058209749445923078164062862089986280348253421170679';
        if ($scale <= 0) return '3';
        $length = $scale + 2;
        return substr($pi, 0, min(strlen($pi), $length));
    }

    private function suffixForRoot(int $degree): string
    {
        if ($degree === 1) return '1st';
        if ($degree === 2) return '2nd';
        if ($degree === 3) return '3rd';
        return $degree . 'th';
    }
}