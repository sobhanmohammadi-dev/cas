<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification\Rules;

use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Simplification\SimplificationRule;

/**
 * Collects like terms in a chain of additions/subtractions: terms that
 * share the same non-numeric "base" (e.g. both are `x`, or both are
 * `sin(x)`) are merged by summing their numeric coefficients --
 * 2x + 4x => 6x, 6 - 4 - 5 => -3, 3x + 2 - x => 2x + 2.
 *
 * Only fires on the outermost Add/Subtract node of a chain (a node whose
 * parent, if any, is not itself an Add/Subtract), so each chain is
 * collected exactly once as a whole rather than piecemeal.
 */
final class LikeTermsRule implements SimplificationRule
{
    public function name(): Translatable
    {
        return Translatable::of('rule.like_terms', 'Combine Like Terms');
    }

    public function apply(Node $node): ?Node
    {
        if (!$node instanceof BinaryNode || !$this->isAdditive($node)) {
            return null;
        }

        $terms = $this->flatten($node);
        if (count($terms) < 2) {
            return null;
        }

        /** @var array<string, array{0:Node|null, 1:Rational}> $groups */
        $groups = [];
        $order = [];
        foreach ($terms as [$base, $coefficient]) {
            $key = $base === null ? '#const' : (string) $base;
            if (!isset($groups[$key])) {
                $groups[$key] = [$base, Rational::fromInt(0)];
                $order[] = $key;
            }
            $groups[$key][1] = $groups[$key][1]->add($coefficient);
        }

        $combinedSomething = false;
        $counts = [];
        foreach ($terms as [$base]) {
            $key = $base === null ? '#const' : (string) $base;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        foreach ($counts as $count) {
            if ($count > 1) {
                $combinedSomething = true;
                break;
            }
        }
        if (!$combinedSomething) {
            return null;
        }

        $rebuilt = $this->rebuild($groups, $order, $node->startPos, $node->endPos);
        return $rebuilt->equals($node) ? null : $rebuilt;
    }

    private function isAdditive(BinaryNode $node): bool
    {
        return $node->operator === BinaryOperator::Add || $node->operator === BinaryOperator::Subtract;
    }

    /** @return array<int, array{0:Node|null,1:Rational}> list of [base, coefficient] */
    private function flatten(Node $node): array
    {
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Add) {
            return [...$this->flatten($node->left), ...$this->flatten($node->right)];
        }
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Subtract) {
            return [...$this->flatten($node->left), ...$this->negateAll($this->flatten($node->right))];
        }
        if ($node instanceof NegateNode) {
            return $this->negateAll($this->flatten($node->operand));
        }
        return [$this->decomposeTerm($node)];
    }

    /** @param array<int, array{0:Node|null,1:Rational}> $terms @return array<int, array{0:Node|null,1:Rational}> */
    private function negateAll(array $terms): array
    {
        return array_map(fn (array $t) => [$t[0], $t[1]->negate()], $terms);
    }

    /** @return array{0:Node|null,1:Rational} */
    private function decomposeTerm(Node $node): array
    {
        if ($node instanceof NumberNode) {
            return [null, $node->value];
        }
        if ($node instanceof BinaryNode && $node->operator === BinaryOperator::Multiply) {
            if ($node->left instanceof NumberNode) {
                return [$node->right, $node->left->value];
            }
            if ($node->right instanceof NumberNode) {
                return [$node->left, $node->right->value];
            }
        }
        return [$node, Rational::fromInt(1)];
    }

    /**
     * @param array<string, array{0:Node|null,1:Rational}> $groups
     * @param string[] $order
     */
    private function rebuild(array $groups, array $order, int $startPos, int $endPos): Node
    {
        $result = null;
        foreach ($order as $key) {
            [$base, $coefficient] = $groups[$key];
            if ($coefficient->isZero() && count($order) > 1) {
                continue;
            }

            $termNode = $this->buildTerm($base, $coefficient, $startPos, $endPos);

            if ($result === null) {
                $result = $termNode;
                continue;
            }

            if ($coefficient->isNegative()) {
                $positiveTerm = $this->buildTerm($base, $coefficient->negate(), $startPos, $endPos);
                $result = new BinaryNode(BinaryOperator::Subtract, $result, $positiveTerm, $startPos, $endPos);
            } else {
                $result = new BinaryNode(BinaryOperator::Add, $result, $termNode, $startPos, $endPos);
            }
        }

        return $result ?? NumberNode::fromInt(0, $startPos, $endPos);
    }

    private function buildTerm(?Node $base, Rational $coefficient, int $startPos, int $endPos): Node
    {
        if ($base === null) {
            return new NumberNode($coefficient, $startPos, $endPos);
        }
        if ($coefficient->isOne()) {
            return $base;
        }
        if ($coefficient->negate()->isOne()) {
            return new NegateNode($base, $startPos, $endPos);
        }
        return new BinaryNode(BinaryOperator::Multiply, new NumberNode($coefficient, $startPos, $endPos), $base, $startPos, $endPos);
    }
}
