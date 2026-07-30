<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Solving;

use Sobhanmohammadi\CAS\Number\Rational;

/**
 * The result of solving an equation for one variable: zero or more exact
 * rational roots, plus optionally a note when roots are irrational/complex
 * and therefore not representable exactly.
 */
final class Solution
{
    /** @param Rational[] $roots */
    public function __construct(
        public readonly array $roots,
        public readonly bool $isIdentity = false,
        public readonly bool $hasNoRealSolution = false,
    ) {
    }

    public static function empty(bool $isIdentity = false, bool $hasNoRealSolution = false): self
    {
        return new self([], $isIdentity, $hasNoRealSolution);
    }

    public static function single(Rational $root): self
    {
        return new self([$root]);
    }
}
