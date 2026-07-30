<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification\Rules;

use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Simplification\SimplificationRule;

/**
 * Distributive property: a*(b+c) => a*b + a*c, a*(b-c) => a*b - a*c, and
 * the same with the sum/difference on the left: (b+c)*a => b*a + c*a.
 */
final class DistributiveRule implements SimplificationRule
{
    public function name(): Translatable
    {
        return Translatable::of('rule.distributive', 'Distributive Property');
    }

    public function apply(Node $node): ?Node
    {
        if (!$node instanceof BinaryNode || $node->operator !== BinaryOperator::Multiply) {
            return null;
        }

        if ($node->right instanceof BinaryNode && $this->isAdditive($node->right)) {
            return $this->distribute($node->left, $node->right, onLeft: true, position: $node);
        }

        if ($node->left instanceof BinaryNode && $this->isAdditive($node->left)) {
            return $this->distribute($node->right, $node->left, onLeft: false, position: $node);
        }

        return null;
    }

    private function isAdditive(BinaryNode $node): bool
    {
        return $node->operator === BinaryOperator::Add || $node->operator === BinaryOperator::Subtract;
    }

    private function distribute(Node $factor, BinaryNode $sum, bool $onLeft, BinaryNode $position): Node
    {
        $left = $onLeft
            ? new BinaryNode(BinaryOperator::Multiply, $factor, $sum->left, $position->startPos, $position->endPos)
            : new BinaryNode(BinaryOperator::Multiply, $sum->left, $factor, $position->startPos, $position->endPos);

        $right = $onLeft
            ? new BinaryNode(BinaryOperator::Multiply, $factor, $sum->right, $position->startPos, $position->endPos)
            : new BinaryNode(BinaryOperator::Multiply, $sum->right, $factor, $position->startPos, $position->endPos);

        return new BinaryNode($sum->operator, $left, $right, $position->startPos, $position->endPos);
    }
}
