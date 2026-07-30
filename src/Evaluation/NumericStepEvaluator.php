<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Evaluation;

use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Exception\DomainException;
use Sobhanmohammadi\CAS\Exception\UnboundVariableException;
use Sobhanmohammadi\CAS\Explain\FinalResult;
use Sobhanmohammadi\CAS\Explain\Step;
use Sobhanmohammadi\CAS\Explain\StepDocument;
use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\ConstantNode;
use Sobhanmohammadi\CAS\Node\FunctionKind;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;
use Sobhanmohammadi\CAS\Number\Rational;

/**
 * Evaluates a numeric expression one operation at a time, in strict
 * precedence order (innermost parentheses/highest-precedence subtrees
 * first -- which the expression tree's shape already encodes), producing
 * a fully narrated StepDocument. Every step shows the sub-expression being
 * reduced, the rule applied, and the whole expression as it stands after
 * that single reduction -- mirroring how a person works through the
 * expression by hand.
 *
 * Uses double-precision floats internally (via NumericEvaluator's rules
 * for domain validation) since transcendental functions are involved;
 * intermediate values are re-inserted into the tree as rounded decimal
 * NumberNodes so later steps can keep operating on the same Node type.
 */
final class NumericStepEvaluator
{
    private const DISPLAY_PRECISION = 6;
    private const MAX_STEPS = 200;

    public function evaluateWithSteps(Node $node, SymbolTable $symbols = new SymbolTable()): StepDocument
    {
        $original = (string) $node;
        $current = $this->substituteVariables($node, $symbols);
        $steps = [];

        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            $reduction = $this->findFirstReducible($current);
            if ($reduction === null) {
                break;
            }
            [$step, $rewritten] = $reduction;
            $steps[] = $step;
            $current = $rewritten;
        }

        $resultValue = $this->render($current);

        return new StepDocument(
            title: Translatable::of('doc.title.numeric_evaluation', 'Step-by-Step Evaluation of a Mathematical Expression'),
            subject: $original,
            goal: Translatable::of('doc.goal.numeric_evaluation', 'Evaluate the expression'),
            orderOfOperations: [
                Translatable::of('order.parentheses', 'Parentheses'),
                Translatable::of('order.exponents', 'Exponents'),
                Translatable::of('order.mul_div', 'Multiplication and Division'),
                Translatable::of('order.add_sub', 'Addition and Subtraction'),
            ],
            steps: $steps,
            finalResult: new FinalResult(
                expression: $resultValue,
                decimal: $resultValue,
                summary: Translatable::of('summary.numeric_evaluation', 'The expression evaluates to {value}.', ['value' => $resultValue]),
            ),
        );
    }

    private function substituteVariables(Node $node, SymbolTable $symbols): Node
    {
        if ($node instanceof VariableNode) {
            $value = $symbols->get($node->name);
            if ($value === null) {
                throw new UnboundVariableException("Variable '{$node->name}' is not bound.");
            }
            return new NumberNode($value, $node->startPos, $node->endPos);
        }
        return $node->withChildren(array_map(
            fn (Node $child) => $this->substituteVariables($child, $symbols),
            $node->children()
        ));
    }

    /** @return array{0:Step,1:Node}|null */
    private function findFirstReducible(Node $node): ?array
    {
        foreach ($node->children() as $index => $child) {
            $childResult = $this->findFirstReducible($child);
            if ($childResult !== null) {
                [$step, $newChild] = $childResult;
                $children = $node->children();
                $children[$index] = $newChild;
                return [$step, $node->withChildren($children)];
            }
        }

        return match (true) {
            $node instanceof BinaryNode && $this->isNumericLeaf($node->left) && $this->isNumericLeaf($node->right)
                => $this->reduceBinary($node),
            $node instanceof NegateNode && $this->isNumericLeaf($node->operand)
                => $this->reduceNegate($node),
            $node instanceof FunctionNode && $this->allNumericLeaves($node->arguments)
                => $this->reduceFunction($node),
            default => null,
        };
    }

    private function isNumericLeaf(Node $node): bool
    {
        return $node instanceof NumberNode || $node instanceof ConstantNode;
    }

    /** @param Node[] $nodes */
    private function allNumericLeaves(array $nodes): bool
    {
        foreach ($nodes as $n) {
            if (!$this->isNumericLeaf($n)) {
                return false;
            }
        }
        return true;
    }

    private function valueOf(Node $node): float
    {
        return match (true) {
            $node instanceof NumberNode => (float) $node->value->toDecimalString(20),
            $node instanceof ConstantNode => $node->kind->approximateValue(),
            default => throw new DomainException('Not a numeric leaf.'),
        };
    }

    private function format(float $value): string
    {
        if (abs($value - round($value)) < 1e-12) {
            return (string) (int) round($value);
        }
        $rounded = round($value, self::DISPLAY_PRECISION);
        return rtrim(rtrim(sprintf('%.' . self::DISPLAY_PRECISION . 'F', $rounded), '0'), '.');
    }

    private function toNode(float $value): NumberNode
    {
        return NumberNode::fromDecimalString($this->format($value));
    }

    /**
     * Renders a tree the same way Node::__toString() does, except numeric
     * leaves are shown as rounded decimals rather than exact fractions --
     * "1.8271" instead of "18271/10000" -- since this evaluator is working
     * in floating point and the fraction form would be misleading noise.
     */
    private function render(Node $node): string
    {
        return match (true) {
            $node instanceof NumberNode => $this->format($this->valueOf($node)),
            $node instanceof VariableNode, $node instanceof ConstantNode => (string) $node,
            $node instanceof NegateNode => '(-' . $this->render($node->operand) . ')',
            $node instanceof BinaryNode => '(' . $this->render($node->left) . ' ' . $node->operator->value . ' ' . $this->render($node->right) . ')',
            $node instanceof FunctionNode => $node->kind->value . '(' . implode(', ', array_map($this->render(...), $node->arguments)) . ')',
            default => (string) $node,
        };
    }

    /** @return array{0:Step,1:Node} */
    private function reduceBinary(BinaryNode $node): array
    {
        $a = $this->valueOf($node->left);
        $b = $this->valueOf($node->right);
        $aStr = $this->format($a);
        $bStr = $this->format($b);

        [$rule, $formula, $result, $details] = match ($node->operator) {
            BinaryOperator::Add => [
                Translatable::of('rule.addition', 'Addition'),
                Translatable::of('formula.addition', 'a + b'),
                $a + $b,
                [],
            ],
            BinaryOperator::Subtract => [
                Translatable::of('rule.subtraction', 'Subtraction'),
                Translatable::of('formula.subtraction', 'a - b'),
                $a - $b,
                [],
            ],
            BinaryOperator::Multiply => [
                Translatable::of('rule.multiplication', 'Multiplication'),
                Translatable::of('formula.multiplication', 'a * b'),
                $a * $b,
                [],
            ],
            BinaryOperator::Divide => [
                Translatable::of('rule.division', 'Division'),
                Translatable::of('formula.division', 'a / b'),
                $b == 0.0 ? throw new DivisionByZeroException('Division by zero.') : $a / $b,
                [],
            ],
            BinaryOperator::Power => $this->reducePower($a, $b),
        };

        $resultStr = $this->format($result);
        $newNode = $this->toNode($result);
        $calculation = "{$aStr} {$node->operator->value} {$bStr} = {$resultStr}";

        $step = new Step(
            title: $rule,
            currentExpression: $this->render($node),
            rule: $rule,
            result: $resultStr,
            updatedExpression: $resultStr,
            targetExpression: $this->render($node),
            formula: $formula,
            calculation: $calculation,
            details: $details,
        );

        return [$step, $newNode];
    }

    /** @return array{0:Translatable,1:Translatable,2:float,3:array<string,string>} */
    private function reducePower(float $base, float $exponent): array
    {
        $rule = Translatable::of('rule.exponentiation', 'Exponentiation');

        if ($base === 0.0 && $exponent < 0) {
            throw new DivisionByZeroException('0 cannot be raised to a negative power.');
        }
        if ($base < 0 && floor($exponent) !== $exponent) {
            throw new DomainException('Cannot raise a negative number to a non-integer power.');
        }

        if ($base > 0) {
            $lnA = log($base);
            $bTimesLnA = $exponent * $lnA;
            $result = exp($bTimesLnA);
            $formula = Translatable::of('formula.power_via_exp_ln', 'a^b = e^(b * ln(a))');
            $details = [
                'a' => $this->format($base),
                'b' => $this->format($exponent),
                'ln_a' => $this->format($lnA),
                'b_times_ln_a' => $this->format($bTimesLnA),
            ];
            return [$rule, $formula, $result, $details];
        }

        return [$rule, Translatable::of('formula.power', 'a^b'), $base ** $exponent, []];
    }

    /** @return array{0:Step,1:Node} */
    private function reduceNegate(NegateNode $node): array
    {
        $value = $this->valueOf($node->operand);
        $result = -$value;
        $resultStr = $this->format($result);
        $newNode = $this->toNode($result);
        $rule = Translatable::of('rule.negation', 'Negation');

        $step = new Step(
            title: $rule,
            currentExpression: $this->render($node),
            rule: $rule,
            result: $resultStr,
            updatedExpression: $resultStr,
            targetExpression: $this->render($node),
            formula: Translatable::of('formula.negation', '-a'),
            calculation: "-({$this->format($value)}) = {$resultStr}",
        );

        return [$step, $newNode];
    }

    /** @return array{0:Step,1:Node} */
    private function reduceFunction(FunctionNode $node): array
    {
        $args = array_map($this->valueOf(...), $node->arguments);
        $argStrs = array_map($this->format(...), $args);

        [$rule, $formula, $result] = $this->applyFunction($node->kind, $args);
        $resultStr = $this->format($result);
        $newNode = $this->toNode($result);

        $step = new Step(
            title: $rule,
            currentExpression: $this->render($node),
            rule: $rule,
            result: $resultStr,
            updatedExpression: $resultStr,
            targetExpression: $this->render($node),
            formula: $formula,
            calculation: $node->kind->value . '(' . implode(', ', $argStrs) . ') = ' . $resultStr,
        );

        return [$step, $newNode];
    }

    /** @param float[] $args @return array{0:Translatable,1:Translatable,2:float} */
    private function applyFunction(FunctionKind $kind, array $args): array
    {
        return match ($kind) {
            FunctionKind::Sin => [Translatable::of('rule.sine', 'Sine'), Translatable::of('formula.sine', 'sin(x)'), sin($args[0])],
            FunctionKind::Cos => [Translatable::of('rule.cosine', 'Cosine'), Translatable::of('formula.cosine', 'cos(x)'), cos($args[0])],
            FunctionKind::Tan => [Translatable::of('rule.tangent', 'Tangent'), Translatable::of('formula.tangent', 'tan(x)'), tan($args[0])],
            FunctionKind::Asin => [Translatable::of('rule.arcsine', 'Arcsine'), Translatable::of('formula.arcsine', 'asin(x)'), $this->boundedAsin($args[0])],
            FunctionKind::Acos => [Translatable::of('rule.arccosine', 'Arccosine'), Translatable::of('formula.arccosine', 'acos(x)'), $this->boundedAcos($args[0])],
            FunctionKind::Atan => [Translatable::of('rule.arctangent', 'Arctangent'), Translatable::of('formula.arctangent', 'atan(x)'), atan($args[0])],
            FunctionKind::Atan2 => [Translatable::of('rule.arctangent2', 'Two-argument Arctangent'), Translatable::of('formula.arctangent2', 'atan2(y, x)'), atan2($args[0], $args[1])],
            FunctionKind::Sqrt => [
                Translatable::of('rule.square_root', 'Square Root'),
                Translatable::of('formula.square_root', 'sqrt(x)'),
                $args[0] < 0 ? throw new DomainException('Cannot take the square root of a negative number.') : sqrt($args[0]),
            ],
            FunctionKind::Root => [Translatable::of('rule.nth_root', 'Nth Root'), Translatable::of('formula.nth_root', 'root(x, n)'), $this->nthRoot($args[0], $args[1])],
            FunctionKind::Abs => [Translatable::of('rule.absolute_value', 'Absolute Value'), Translatable::of('formula.absolute_value', '|x|'), abs($args[0])],
            FunctionKind::Ln => [
                Translatable::of('rule.natural_log', 'Natural Logarithm'),
                Translatable::of('formula.natural_log', 'ln(x)'),
                $args[0] <= 0 ? throw new DomainException('ln is only defined for positive numbers.') : log($args[0]),
            ],
            FunctionKind::Log => [
                Translatable::of('rule.logarithm', 'Logarithm'),
                Translatable::of('formula.logarithm', 'log(x, base)'),
                $args[0] <= 0 ? throw new DomainException('log is only defined for positive numbers.') : log($args[0], $args[1]),
            ],
            FunctionKind::Exp => [Translatable::of('rule.exponential', 'Exponential'), Translatable::of('formula.exponential', 'e^x'), exp($args[0])],
        };
    }

    private function boundedAsin(float $x): float
    {
        if ($x < -1 || $x > 1) {
            throw new DomainException('asin is only defined between -1 and 1.');
        }
        return asin($x);
    }

    private function boundedAcos(float $x): float
    {
        if ($x < -1 || $x > 1) {
            throw new DomainException('acos is only defined between -1 and 1.');
        }
        return acos($x);
    }

    private function nthRoot(float $value, float $degree): float
    {
        if ($degree === 0.0) {
            throw new DivisionByZeroException('Root degree cannot be zero.');
        }
        if ($value < 0 && fmod($degree, 2.0) === 0.0) {
            throw new DomainException('Cannot take an even root of a negative number.');
        }
        $sign = $value < 0 ? -1.0 : 1.0;
        return $sign * (abs($value) ** (1.0 / $degree));
    }
}
