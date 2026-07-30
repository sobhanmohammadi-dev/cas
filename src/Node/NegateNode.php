<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

/** Unary minus. */
final class NegateNode extends Node
{
    public function __construct(
        public readonly Node $operand,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public function __toString(): string
    {
        return '(-' . $this->operand . ')';
    }

    public function equals(Node $other): bool
    {
        return $other instanceof self && $this->operand->equals($other->operand);
    }

    public function children(): array
    {
        return [$this->operand];
    }

    public function withChildren(array $children): static
    {
        return new self($children[0], $this->startPos, $this->endPos);
    }
}
