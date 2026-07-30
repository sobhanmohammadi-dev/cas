<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * Rebuilds a StepDocument with every Translatable's text swapped for the
 * matching entry in a language pack (key => localized text), leaving any
 * key not present in the pack as its original (English) default.
 * Mathematical content (expressions, numbers, results) is untouched.
 */
final class Translator
{
    /** @param array<string,string> $languagePack key => localized text */
    public function translate(StepDocument $document, array $languagePack): StepDocument
    {
        return new StepDocument(
            $this->apply($document->title, $languagePack),
            $document->subject,
            $this->apply($document->goal, $languagePack),
            array_map(fn (Translatable $t) => $this->apply($t, $languagePack), $document->orderOfOperations),
            array_map(fn (Step $s) => $this->translateStep($s, $languagePack), $document->steps),
            new FinalResult(
                $document->finalResult->expression,
                $document->finalResult->variable,
                $document->finalResult->exactValues,
                $document->finalResult->decimal,
                $document->finalResult->summary !== null ? $this->apply($document->finalResult->summary, $languagePack) : null,
            ),
        );
    }

    /** @param array<string,string> $languagePack */
    private function translateStep(Step $step, array $languagePack): Step
    {
        return new Step(
            $this->apply($step->title, $languagePack),
            $step->currentExpression,
            $this->apply($step->rule, $languagePack),
            $step->result,
            $step->updatedExpression,
            $step->targetExpression,
            $step->formula !== null ? $this->apply($step->formula, $languagePack) : null,
            $step->calculation,
            $step->details,
        );
    }

    /** @param array<string,string> $languagePack */
    private function apply(Translatable $translatable, array $languagePack): Translatable
    {
        $text = $languagePack[$translatable->key] ?? $translatable->defaultText;
        return $translatable->withText($text);
    }
}
