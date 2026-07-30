<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * A complete worked solution: title, the original expression/equation,
 * the goal, the order of operations applied, every intermediate Step, and
 * the FinalResult. This is the PHP-native structure the reference JSON
 * examples described; JSON is just one possible serialization of it.
 */
final class StepDocument
{
    /**
     * @param Translatable[] $orderOfOperations
     * @param Step[] $steps
     */
    public function __construct(
        public readonly Translatable $title,
        public readonly string $subject,
        public readonly Translatable $goal,
        public readonly array $orderOfOperations,
        public readonly array $steps,
        public readonly FinalResult $finalResult,
    ) {
    }

    /** Plain-array rendering (English, or whatever language the document already carries). */
    public function toArray(): array
    {
        return [
            'title' => $this->title->render(),
            'subject' => $this->subject,
            'goal' => $this->goal->render(),
            'order_of_operations' => array_map(fn (Translatable $t) => $t->render(), $this->orderOfOperations),
            'steps' => array_map($this->stepToArray(...), $this->steps),
            'final_result' => [
                'expression' => $this->finalResult->expression,
                'variable' => $this->finalResult->variable,
                'exact_values' => $this->finalResult->exactValues,
                'decimal' => $this->finalResult->decimal,
                'summary' => $this->finalResult->summary?->render(),
            ],
        ];
    }

    private function stepToArray(Step $step): array
    {
        return [
            'title' => $step->title->render(),
            'current_expression' => $step->currentExpression,
            'target_expression' => $step->targetExpression,
            'rule' => $step->rule->render(),
            'formula' => $step->formula?->render(),
            'calculation' => $step->calculation,
            'details' => $step->details,
            'result' => $step->result,
            'updated_expression' => $step->updatedExpression,
        ];
    }
}
