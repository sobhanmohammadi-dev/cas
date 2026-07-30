<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

final class ConstantNode extends Node
{
    public function __construct(
        public readonly ConstantKind $kind,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public function __toString(): string
    {
        return $this->kind->value;
    }

    public function equals(Node $other): bool
    {
        return $other instanceof self && $this->kind === $other->kind;
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
