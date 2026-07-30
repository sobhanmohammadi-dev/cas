<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

use Sobhanmohammadi\CAS\Number\Rational;

/**
 * A single exact numeric literal (integer or rational). Replaces the old
 * IntegerNode/RationalNode/NumericNode hierarchy: one node, one value type.
 */
final class NumberNode extends Node
{
    public function __construct(
        public readonly Rational $value,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public static function fromInt(int $value, int $startPos = 0, int $endPos = 0): self
    {
        return new self(Rational::fromInt($value), $startPos, $endPos);
    }

    public static function fromDecimalString(string $raw, int $startPos = 0, int $endPos = 0): self
    {
        return new self(Rational::fromDecimalString($raw), $startPos, $endPos);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isOne(): bool
    {
        return $this->value->isOne();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function toMathString(): string
    {
        return $this->value->toMathString();
    }

    public function __toString(): string
    {
        return $this->value->toMathString();
    }

    public function equals(Node $other): bool
    {
        return $other instanceof self && $this->value->equals($other->value);
    }

    public function children(): array
    {
        return [];
    }

    public function withChildren(array $children): static
    {
        return $this;
    }
}
