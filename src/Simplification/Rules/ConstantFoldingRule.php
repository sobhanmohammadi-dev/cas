<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Simplification\Rules;

use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Exception\CasException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Simplification\SimplificationRule;

/** Folds any subtree made entirely of numeric literals into a single NumberNode. */
final class ConstantFoldingRule implements SimplificationRule
{
    private readonly ExactEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new ExactEvaluator();
    }

    public function name(): \Sobhanmohammadi\CAS\Explain\Translatable
    {
        return \Sobhanmohammadi\CAS\Explain\Translatable::of('rule.constant_folding', 'Evaluate the numeric expression');
    }

    public function apply(Node $node): ?Node
    {
        if (!$this->isPurelyNumeric($node) || $node instanceof NumberNode) {
            return null;
        }

        try {
            $value = $this->evaluator->evaluate($node);
        } catch (CasException) {
            return null;
        }

        return new NumberNode($value, $node->startPos, $node->endPos);
    }

    private function isPurelyNumeric(Node $node): bool
    {
        return match (true) {
            $node instanceof NumberNode => true,
            $node instanceof NegateNode => $this->isPurelyNumeric($node->operand),
            $node instanceof BinaryNode => $this->isPurelyNumeric($node->left) && $this->isPurelyNumeric($node->right),
            default => false,
        };
    }
}
