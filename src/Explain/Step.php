<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * One recorded step in a worked solution. Mathematical fields (expressions,
 * results) are plain strings; narrative fields (title, rule, formula) are
 * Translatable so a language pack can localize them without touching math.
 *
 * @property-read array<string,string> $details Extra plain key/value pairs
 *                 for numeric steps (e.g. ['a' => '2', 'ln_a' => '0.693...']).
 *                 Keys are technical labels, not narration, and are left
 *                 untranslated by design.
 */
final class Step
{
    /** @param array<string,string> $details */
    public function __construct(
        public readonly Translatable $title,
        public readonly string $currentExpression,
        public readonly Translatable $rule,
        public readonly string $result,
        public readonly string $updatedExpression,
        public readonly ?string $targetExpression = null,
        public readonly ?Translatable $formula = null,
        public readonly ?string $calculation = null,
        public readonly array $details = [],
    ) {
    }
}
