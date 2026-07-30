<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * The terminal outcome of a worked solution: either a simplified
 * expression, a solved variable, or both. Fields that don't apply to a
 * given document are left null.
 */
final class FinalResult
{
    public function __construct(
        public readonly ?string $expression = null,
        public readonly ?string $variable = null,
        /** @var string[] */
        public readonly array $exactValues = [],
        public readonly ?string $decimal = null,
        public readonly ?Translatable $summary = null,
    ) {
    }
}
