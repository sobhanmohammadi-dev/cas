<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification;

use Sobhanmohammadi\CAS\Explain\SimplificationTrace;
use Sobhanmohammadi\CAS\Explain\Step;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Simplification\Rules\ConstantFoldingRule;
use Sobhanmohammadi\CAS\Simplification\Rules\DistributiveRule;
use Sobhanmohammadi\CAS\Simplification\Rules\IdentityRule;
use Sobhanmohammadi\CAS\Simplification\Rules\LikeTermsRule;

/**
 * Repeatedly rewrites an expression tree bottom-up using a list of
 * {@see SimplificationRule}s until a fixed point is reached (i.e. a full
 * pass leaves the tree unchanged), with a safety cap on iterations.
 */
final class Simplifier
{
    private const MAX_PASSES = 64;

    /** @var SimplificationRule[] */
    private readonly array $rules;

    /** @param SimplificationRule[] $rules */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules ?: [
            new ConstantFoldingRule(),
            new DistributiveRule(),
            new LikeTermsRule(),
            new IdentityRule(),
        ];
    }

    public function simplify(Node $node): Node
    {
        $current = $node;
        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $next = $this->onePass($current);
            if ($next->equals($current)) {
                return $next;
            }
            $current = $next;
        }
        return $current;
    }

    /**
     * Like simplify(), but records every individual rewrite as a Step so
     * callers can render a step-by-step explanation. Repeatedly finds and
     * applies the first matching rewrite anywhere in the tree (innermost
     * first) until no rule applies anywhere.
     */
    public function simplifyWithSteps(Node $node): SimplificationTrace
    {
        $current = $node;
        $steps = [];

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $found = $this->findFirstRewrite($current);
            if ($found === null) {
                break;
            }
            [$rule, $rewritten] = $found;
            $steps[] = new Step(
                title: $rule,
                currentExpression: (string) $current,
                rule: $rule,
                result: (string) $rewritten,
                updatedExpression: (string) $rewritten,
            );
            $current = $rewritten;
        }

        return new SimplificationTrace($current, $steps);
    }

    /** @return array{0:\Sobhanmohammadi\CAS\Explain\Translatable,1:Node}|null [rule, whole tree after applying it once] */
    private function findFirstRewrite(Node $node): ?array
    {
        if ($node instanceof EquationNode) {
            $left = $this->findFirstRewrite($node->left);
            if ($left !== null) {
                return [$left[0], new EquationNode($left[1], $node->right, $node->startPos, $node->endPos)];
            }
            $right = $this->findFirstRewrite($node->right);
            if ($right !== null) {
                return [$right[0], new EquationNode($node->left, $right[1], $node->startPos, $node->endPos)];
            }
            return null;
        }

        foreach ($node->children() as $index => $child) {
            $childResult = $this->findFirstRewrite($child);
            if ($childResult !== null) {
                $newChildren = $node->children();
                $newChildren[$index] = $childResult[1];
                return [$childResult[0], $node->withChildren($newChildren)];
            }
        }

        foreach ($this->rules as $rule) {
            $rewritten = $rule->apply($node);
            if ($rewritten !== null && !$rewritten->equals($node)) {
                return [$rule->name(), $rewritten];
            }
        }

        return null;
    }

    private function onePass(Node $node): Node
    {
        if ($node instanceof EquationNode) {
            return new EquationNode(
                $this->onePass($node->left),
                $this->onePass($node->right),
                $node->startPos,
                $node->endPos
            );
        }

        $children = array_map($this->onePass(...), $node->children());
        $rebuilt = $node->withChildren($children);

        foreach ($this->rules as $rule) {
            $rewritten = $rule->apply($rebuilt);
            if ($rewritten !== null && !$rewritten->equals($rebuilt)) {
                return $rewritten;
            }
        }

        return $rebuilt;
    }
}
