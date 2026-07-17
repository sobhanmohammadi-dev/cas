<?php
namespace Sobhanmohammadi\CAS\StepExplainer;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode, RationalNode, ComplexNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode, PiNode, VariableNode,
    BinaryOperatorNode
};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Services\SymbolTable;

class StepEvaluator
{
    private SymbolTable   $symbolTable;
    private int           $scale;
    private StepRecorder  $recorder;

    public function __construct(SymbolTable $symbolTable, int $scale = 5)
    {
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('BCMath extension is required for StepEvaluator.');
        }
        $this->symbolTable = $symbolTable;
        $this->scale       = $scale;
        $this->recorder    = new StepRecorder();
    }

    /** @return StepText[] */
    public function evaluateExpression(string $expression): array
    {
        $this->recorder->reset();

        $lexer  = new Lexer($expression);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $expression);
        $ast    = $parser->parse();

        $this->recorder->record(StepExplainer::expressionStart($expression));

        $result = $this->evalNode($ast);

        $this->recorder->record(StepExplainer::finalExpressionResult($result));

        return $this->recorder->getSteps();
    }

    // ─── Node dispatch ────────────────────────────────────────────────

    private function evalNode(MathNode $node): string
    {
        if ($node instanceof IntegerNode) {
            return \gmp_strval($node->getValue());
        }

        if ($node instanceof RationalNode) {
            return bcdiv(
                \gmp_strval($node->getValueOfNumerator()),
                \gmp_strval($node->getValueOfDenominator()),
                $this->scale
            );
        }

        if ($node instanceof ComplexNode) {
            throw new \RuntimeException('Complex numbers are not supported in StepEvaluator.');
        }

        if ($node instanceof PiNode) {
            $pi = self::piString($this->scale);
            $this->recorder->record(StepExplainer::piSubstitution($pi));
            return $pi;
        }

        if ($node instanceof VariableNode) {
            return $this->evalVariable($node);
        }

        if ($node instanceof UnaryNode) {
            return $this->evalUnary($node);
        }

        if ($node instanceof BinaryOperatorNode) {
            return $this->evalBinaryOp($node);
        }

        if ($node instanceof SqrtNode) {
            return $this->evalSqrt($node);
        }

        if ($node instanceof RootNode) {
            return $this->evalRoot($node);
        }

        throw new \RuntimeException('Unsupported node type: ' . get_class($node));
    }

    // ─── Leaves ───────────────────────────────────────────────────────

    private function evalVariable(VariableNode $node): string
    {
        $name  = $node->getName();
        $value = $this->symbolTable->lookup($name);
        if ($value === null) {
            throw new \RuntimeException("Undefined variable '{$name}'.");
        }
        $valStr = $this->evalNode($value);
        $this->recorder->record(StepExplainer::variableSubstitution($name, $valStr));
        return $valStr;
    }

    private function evalUnary(UnaryNode $node): string
    {
        if ($node->getOp() !== '-') {
            throw new \RuntimeException('Only unary minus is supported.');
        }
        $operandStr = $this->evalNode($node->getOperand());
        $result     = bcmul('-1', $operandStr, $this->scale);
        $this->recorder->record(StepExplainer::unaryNegation($operandStr, $result));
        return $result;
    }

    // ─── Binary operators ─────────────────────────────────────────────

    private function evalBinaryOp(BinaryOperatorNode $node): string
    {
        // Optimisation: collapse pure-constant subtrees in one step
        if ($this->isConstantSubtree($node)) {
            $result  = $this->computeConstant($node);
            $exprStr = $node->__toString();
            $this->recorder->record(StepExplainer::constantChain($exprStr, $result));
            return $result;
        }

        $leftStr  = $this->evalNode($node->getLeft());
        $rightStr = $this->evalNode($node->getRight());

        if ($node instanceof PlusNode) {
            $result  = bcadd($leftStr, $rightStr, $this->scale);
            $rIsNeg  = bccomp($rightStr, '0', $this->scale) < 0;
            $this->recorder->record(StepExplainer::addition($leftStr, $rightStr, $result, $rIsNeg));
            return $result;
        }

        if ($node instanceof MinusNode) {
            $result  = bcsub($leftStr, $rightStr, $this->scale);
            $rIsNeg  = bccomp($rightStr, '0', $this->scale) < 0;
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
            return $this->evalPower($leftStr, $rightStr);
        }

        throw new \RuntimeException('Unsupported binary operator: ' . get_class($node));
    }

    private function evalPower(string $leftStr, string $rightStr): string
    {
        if (preg_match('/^[+-]?\d+$/', $rightStr)) {
            $result = bcpow($leftStr, $rightStr, $this->scale);
        } else {
            $floatResult = pow((float) $leftStr, (float) $rightStr);
            if (is_nan($floatResult) || is_infinite($floatResult)) {
                throw new \RuntimeException('Exponentiation resulted in NaN or INF.');
            }
            $result = number_format($floatResult, $this->scale, '.', '');
        }
        $type = StepExplainer::powTypeDescription($leftStr, $result, (float) $rightStr);
        $this->recorder->record(
            StepExplainer::exponentiation($leftStr, $rightStr, $result, $type['en'], $type['fa'])
        );
        return $result;
    }

    // ─── Sqrt / Root ──────────────────────────────────────────────────

    private function evalSqrt(SqrtNode $node): string
    {
        $radStr = $this->evalNode($node->getRadicand());
        if (bccomp($radStr, '0', $this->scale) < 0) {
            $this->recorder->record(StepExplainer::errorImaginarySqrt($radStr));
            throw new \RuntimeException('Square root of negative number.');
        }
        $result  = bcsqrt($radStr, $this->scale);
        $perfect = $this->isPerfectSquareIntegerNode($node->getRadicand());
        $this->recorder->record(
            StepExplainer::sqrtOperation($radStr, $result, $perfect, $this->scale)
        );
        return $result;
    }

    private function evalRoot(RootNode $node): string
    {
        $degStr = $this->evalNode($node->getDegree());
        $radStr = $this->evalNode($node->getRadicand());
        if (bccomp($degStr, '0', $this->scale) === 0) {
            throw new \RuntimeException('Root degree cannot be zero.');
        }
        $result = $this->bcNthRoot($radStr, $degStr, $this->scale);
        $suffix = $this->ordinalSuffix((int) $degStr);
        $this->recorder->record(
            StepExplainer::radicalOperation($degStr, $radStr, $result, $suffix)
        );
        return $result;
    }

    /**
     * Computes the real nth root of $rad to $scale decimal digits using
     * Newton's method with bcmath, since bcpow() does not support a
     * fractional exponent (bcpow($rad, 1/$deg, ...) either truncates the
     * exponent to an integer or, on modern bcmath, raises a ValueError).
     *
     * Iteration: x_{k+1} = ((n-1)*x_k + rad / x_k^(n-1)) / n
     */
    private function bcNthRoot(string $rad, string $deg, int $scale): string
    {
        $n = (int) $deg;
        if ($n === 1) {
            return bcadd($rad, '0', $scale);
        }

        $negative = bccomp($rad, '0', $scale) < 0;
        if ($negative) {
            if ($n % 2 === 0) {
                throw new \RuntimeException('Even root of a negative number is not real.');
            }
            $rad = bcmul($rad, '-1', $scale);
        }
        if (bccomp($rad, '0', $scale) === 0) {
            return bcadd('0', '0', $scale);
        }

        $working  = $scale + 10;
        $guess    = pow((float) $rad, 1.0 / $n);
        $x        = number_format((!is_finite($guess) || $guess <= 0) ? 1.0 : $guess, $working, '.', '');
        $nMinus1  = (string) ($n - 1);

        for ($i = 0; $i < 100; $i++) {
            $xPow = bcpow($x, $nMinus1, $working);
            if (bccomp($xPow, '0', $working) === 0) {
                $x = bcadd($x, '0.0000000001', $working);
                continue;
            }
            $next = bcdiv(
                bcadd(bcmul($nMinus1, $x, $working), bcdiv($rad, $xPow, $working), $working),
                (string) $n,
                $working
            );
            $converged = bccomp($next, $x, $scale + 2) === 0;
            $x = $next;
            if ($converged) {
                break;
            }
        }

        $result = bcadd($x, '0', $scale);
        return $negative ? bcmul($result, '-1', $scale) : $result;
    }

    // ─── Constant-subtree helpers ─────────────────────────────────────

    /**
     * True when the subtree contains only numeric literals
     * (no variables, π, or complex nodes).
     */
    private function isConstantSubtree(MathNode $node): bool
    {
        if ($node instanceof IntegerNode || $node instanceof RationalNode) {
            return true;
        }
        if ($node instanceof PiNode
            || $node instanceof VariableNode
            || $node instanceof ComplexNode
        ) {
            return false;
        }
        if ($node instanceof UnaryNode) {
            return $this->isConstantSubtree($node->getOperand());
        }
        if ($node instanceof BinaryOperatorNode) {
            return $this->isConstantSubtree($node->getLeft())
                && $this->isConstantSubtree($node->getRight());
        }
        if ($node instanceof SqrtNode) {
            return $this->isConstantSubtree($node->getRadicand());
        }
        if ($node instanceof RootNode) {
            return $this->isConstantSubtree($node->getDegree())
                && $this->isConstantSubtree($node->getRadicand());
        }
        return false;
    }

    /** Evaluate a constant subtree using bcmath (no step recording). */
    private function computeConstant(MathNode $node): string
    {
        if ($node instanceof IntegerNode) {
            return \gmp_strval($node->getValue());
        }
        if ($node instanceof RationalNode) {
            return bcdiv(
                \gmp_strval($node->getValueOfNumerator()),
                \gmp_strval($node->getValueOfDenominator()),
                $this->scale
            );
        }
        if ($node instanceof UnaryNode && $node->getOp() === '-') {
            return bcmul('-1', $this->computeConstant($node->getOperand()), $this->scale);
        }
        if ($node instanceof PlusNode) {
            return bcadd(
                $this->computeConstant($node->getLeft()),
                $this->computeConstant($node->getRight()),
                $this->scale
            );
        }
        if ($node instanceof MinusNode) {
            return bcsub(
                $this->computeConstant($node->getLeft()),
                $this->computeConstant($node->getRight()),
                $this->scale
            );
        }
        if ($node instanceof MultiplyNode) {
            return bcmul(
                $this->computeConstant($node->getLeft()),
                $this->computeConstant($node->getRight()),
                $this->scale
            );
        }
        if ($node instanceof DivideNode) {
            $den = $this->computeConstant($node->getRight());
            if (bccomp($den, '0', $this->scale) === 0) {
                throw new \RuntimeException('Division by zero in constant expression.');
            }
            return bcdiv($this->computeConstant($node->getLeft()), $den, $this->scale);
        }
        if ($node instanceof PowerNode) {
            $l = $this->computeConstant($node->getLeft());
            $r = $this->computeConstant($node->getRight());
            if (preg_match('/^[+-]?\d+$/', $r)) {
                return bcpow($l, $r, $this->scale);
            }
            $f = pow((float) $l, (float) $r);
            if (is_nan($f) || is_infinite($f)) {
                throw new \RuntimeException('Exponentiation resulted in NaN or INF.');
            }
            return number_format($f, $this->scale, '.', '');
        }
        if ($node instanceof SqrtNode) {
            $rad = $this->computeConstant($node->getRadicand());
            if (bccomp($rad, '0', $this->scale) < 0) {
                throw new \RuntimeException('Square root of negative constant.');
            }
            return bcsqrt($rad, $this->scale);
        }
        if ($node instanceof RootNode) {
            $deg = $this->computeConstant($node->getDegree());
            $rad = $this->computeConstant($node->getRadicand());
            if (bccomp($deg, '0', $this->scale) === 0) {
                throw new \RuntimeException('Root degree cannot be zero.');
            }
            return $this->bcNthRoot($rad, $deg, $this->scale);
        }
        throw new \RuntimeException('Unsupported constant node: ' . get_class($node));
    }

    private function isPerfectSquareIntegerNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_perfect_square($node->getValue());
    }

    // ─── Static helpers ───────────────────────────────────────────────

    /**
     * Returns π to $scale decimal places from a hard-coded 100-digit string.
     * The string has 99 decimal digits, so $scale is clamped accordingly.
     */
    private static function piString(int $scale): string
    {
        // 100 significant digits of π (1 integer + 99 decimal digits)
        $pi = '3.14159265358979323846264338327950288419716939937510'
            . '58209749445923078164062862089986280348253421170679';

        if ($scale <= 0) {
            return '3';
        }
        // "3." + $scale digits, but cap at what we have stored
        $maxScale = strlen($pi) - 2;        // subtract "3."
        $digits   = min($scale, $maxScale);
        return substr($pi, 0, $digits + 2); // +2 for "3."
    }

    private function ordinalSuffix(int $degree): string
    {
        if ($degree === 1) return '1st';
        if ($degree === 2) return '2nd';
        if ($degree === 3) return '3rd';
        return $degree . 'th';
    }
}
