<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * Walks a StepDocument and collects every Translatable's key => default
 * (English) text. Hand this map to a translator; they return the same
 * keys with localized text, which Translator then applies.
 *
 * Only the template text is collected -- interpolation params (numbers,
 * expressions) are never included, since they are not human language.
 */
final class LocalizationExtractor
{
    /** @return array<string,string> key => default English text */
    public function extract(StepDocument $document): array
    {
        $catalog = [];

        $this->collect($document->title, $catalog);
        $this->collect($document->goal, $catalog);
        foreach ($document->orderOfOperations as $item) {
            $this->collect($item, $catalog);
        }

        foreach ($document->steps as $step) {
            $this->collect($step->title, $catalog);
            $this->collect($step->rule, $catalog);
            if ($step->formula !== null) {
                $this->collect($step->formula, $catalog);
            }
        }

        if ($document->finalResult->summary !== null) {
            $this->collect($document->finalResult->summary, $catalog);
        }

        return $catalog;
    }

    /** @param array<string,string> $catalog */
    private function collect(Translatable $translatable, array &$catalog): void
    {
        $catalog[$translatable->key] ??= $translatable->defaultText;
    }
}
