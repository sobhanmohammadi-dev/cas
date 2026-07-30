<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Evaluation;

use Sobhanmohammadi\CAS\Exception\DomainException;
use Sobhanmohammadi\CAS\Exception\UnboundVariableException;
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\ConstantNode;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;
use Sobhanmohammadi\CAS\Number\Rational;

/**
 * Evaluates an expression to an exact Rational whenever that is possible
 * (i.e. the expression uses only +, -, *, /, ^ and integer powers/roots
 * of rationals). Throws UnsupportedOperationException for anything that
 * requires a transcendental function or an inexact root — use
 * {@see NumericEvaluator} for those.
 */
final class ExactEvaluator
{
    public function evaluate(Node $node, SymbolTable $symbols = new SymbolTable()): Rational
    {
        return match (true) {
            $node instanceof NumberNode => $node->value,
            $node instanceof VariableNode => $this->lookup($node, $symbols),
            $node instanceof NegateNode => $this->evaluate($node->operand, $symbols)->negate(),
            $node instanceof BinaryNode => $this->evaluateBinary($node, $symbols),
            $node instanceof FunctionNode, $node instanceof ConstantNode =>
                throw new UnsupportedOperationException('Expression is not exactly rational; use NumericEvaluator.'),
            default => throw new DomainException('Cannot exactly evaluate node of type ' . $node::class),
        };
    }

    private function lookup(VariableNode $node, SymbolTable $symbols): Rational
    {
        $value = $symbols->get($node->name);
        if ($value === null) {
            throw new UnboundVariableException("Variable '{$node->name}' is not bound.");
        }
        return $value;
    }

    private function evaluateBinary(BinaryNode $node, SymbolTable $symbols): Rational
    {
        $left = $this->evaluate($node->left, $symbols);

        if ($node->operator === BinaryOperator::Power) {
            $exponentValue = $this->evaluate($node->right, $symbols);
            if (!$exponentValue->isInteger()) {
                throw new UnsupportedOperationException('Only integer exponents are supported for exact evaluation.');
            }
            return $left->pow($exponentValue->toInt());
        }

        $right = $this->evaluate($node->right, $symbols);

        return match ($node->operator) {
            BinaryOperator::Add => $left->add($right),
            BinaryOperator::Subtract => $left->sub($right),
            BinaryOperator::Multiply => $left->mul($right),
            BinaryOperator::Divide => $left->div($right),
            BinaryOperator::Power => throw new DomainException('unreachable'),
        };
    }
}
