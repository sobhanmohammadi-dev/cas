<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Evaluation;

use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Exception\DomainException;
use Sobhanmohammadi\CAS\Exception\UnboundVariableException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\ConstantNode;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\FunctionKind;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;

/**
 * Evaluates an expression tree to a double-precision approximation.
 * Used whenever transcendental functions (sin, ln, ...) make an exact
 * rational result impossible; for purely rational expressions prefer
 * {@see ExactEvaluator}.
 */
final class NumericEvaluator
{
    public function evaluate(Node $node, SymbolTable $symbols = new SymbolTable()): float
    {
        return match (true) {
            $node instanceof NumberNode => (float) $node->value->toDecimalString(20),
            $node instanceof ConstantNode => $node->kind->approximateValue(),
            $node instanceof VariableNode => $this->lookup($node, $symbols),
            $node instanceof NegateNode => -$this->evaluate($node->operand, $symbols),
            $node instanceof BinaryNode => $this->evaluateBinary($node, $symbols),
            $node instanceof FunctionNode => $this->evaluateFunction($node, $symbols),
            $node instanceof EquationNode => throw new DomainException('An equation has no single numeric value; solve it instead.'),
            default => throw new DomainException('Cannot numerically evaluate node of type ' . $node::class),
        };
    }

    private function lookup(VariableNode $node, SymbolTable $symbols): float
    {
        $value = $symbols->get($node->name);
        if ($value === null) {
            throw new UnboundVariableException("Variable '{$node->name}' is not bound.");
        }
        return (float) $value->toDecimalString(20);
    }

    private function evaluateBinary(BinaryNode $node, SymbolTable $symbols): float
    {
        $left = $this->evaluate($node->left, $symbols);
        $right = $this->evaluate($node->right, $symbols);

        return match ($node->operator) {
            BinaryOperator::Add => $left + $right,
            BinaryOperator::Subtract => $left - $right,
            BinaryOperator::Multiply => $left * $right,
            BinaryOperator::Divide => $right == 0.0
                ? throw new DivisionByZeroException('Division by zero.')
                : $left / $right,
            BinaryOperator::Power => $this->power($left, $right),
        };
    }

    private function power(float $base, float $exponent): float
    {
        if ($base === 0.0 && $exponent < 0) {
            throw new DivisionByZeroException('0 cannot be raised to a negative power.');
        }
        if ($base < 0 && floor($exponent) !== $exponent) {
            throw new DomainException('Cannot raise a negative number to a non-integer power.');
        }
        return $base ** $exponent;
    }

    private function evaluateFunction(FunctionNode $node, SymbolTable $symbols): float
    {
        $args = array_map(fn (Node $n) => $this->evaluate($n, $symbols), $node->arguments);

        return match ($node->kind) {
            FunctionKind::Sin => sin($args[0]),
            FunctionKind::Cos => cos($args[0]),
            FunctionKind::Tan => tan($args[0]),
            FunctionKind::Asin => $this->domainGuard($args[0], -1.0, 1.0, 'asin') ?? asin($args[0]),
            FunctionKind::Acos => $this->domainGuard($args[0], -1.0, 1.0, 'acos') ?? acos($args[0]),
            FunctionKind::Atan => atan($args[0]),
            FunctionKind::Atan2 => atan2($args[0], $args[1]),
            FunctionKind::Sqrt => $args[0] < 0
                ? throw new DomainException('Cannot take the square root of a negative number.')
                : sqrt($args[0]),
            FunctionKind::Root => $this->nthRoot($args[0], $args[1]),
            FunctionKind::Abs => abs($args[0]),
            FunctionKind::Ln => $args[0] <= 0
                ? throw new DomainException('ln is only defined for positive numbers.')
                : log($args[0]),
            FunctionKind::Log => $args[0] <= 0
                ? throw new DomainException('log is only defined for positive numbers.')
                : log($args[0], $args[1]),
            FunctionKind::Exp => exp($args[0]),
        };
    }

    private function domainGuard(float $value, float $min, float $max, string $fn): ?float
    {
        if ($value < $min || $value > $max) {
            throw new DomainException("{$fn} is only defined between {$min} and {$max}.");
        }
        return null;
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
