<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification\Rules;

use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Simplification\SimplificationRule;

/**
 * Classic algebraic identities: x+0, 0+x, x*1, 1*x, x*0, 0*x, x/1, x^1,
 * x^0, 0^n, double negation, and negation-of-zero.
 */
final class IdentityRule implements SimplificationRule
{
    public function name(): \Sobhanmohammadi\CAS\Explain\Translatable
    {
        return \Sobhanmohammadi\CAS\Explain\Translatable::of('rule.identity', 'Apply an algebraic identity');
    }

    public function apply(Node $node): ?Node
    {
        if ($node instanceof NegateNode) {
            if ($node->operand instanceof NegateNode) {
                return $node->operand->operand;
            }
            if ($node->operand instanceof NumberNode && $node->operand->isZero()) {
                return $node->operand;
            }
            return null;
        }

        if (!$node instanceof BinaryNode) {
            return null;
        }

        $left = $node->left;
        $right = $node->right;
        $leftIsZero = $left instanceof NumberNode && $left->isZero();
        $rightIsZero = $right instanceof NumberNode && $right->isZero();
        $leftIsOne = $left instanceof NumberNode && $left->isOne();
        $rightIsOne = $right instanceof NumberNode && $right->isOne();

        return match ($node->operator) {
            BinaryOperator::Add => match (true) {
                $leftIsZero => $right,
                $rightIsZero => $left,
                default => null,
            },
            BinaryOperator::Subtract => match (true) {
                $rightIsZero => $left,
                $leftIsZero => new NegateNode($right, $node->startPos, $node->endPos),
                default => null,
            },
            BinaryOperator::Multiply => match (true) {
                $leftIsZero, $rightIsZero => NumberNode::fromInt(0, $node->startPos, $node->endPos),
                $leftIsOne => $right,
                $rightIsOne => $left,
                default => null,
            },
            BinaryOperator::Divide => match (true) {
                $rightIsZero => throw new DivisionByZeroException('Division by zero in simplification.'),
                $leftIsZero => NumberNode::fromInt(0, $node->startPos, $node->endPos),
                $rightIsOne => $left,
                default => null,
            },
            BinaryOperator::Power => match (true) {
                $rightIsZero && $leftIsZero => throw new DivisionByZeroException('0^0 is undefined.'),
                $rightIsZero => NumberNode::fromInt(1, $node->startPos, $node->endPos),
                $rightIsOne => $left,
                $leftIsZero => NumberNode::fromInt(0, $node->startPos, $node->endPos),
                $leftIsOne => NumberNode::fromInt(1, $node->startPos, $node->endPos),
                default => null,
            },
        };
    }
}
