<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

final class VariableNode extends Node
{
    public function __construct(
        public readonly string $name,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function equals(Node $other): bool
    {
        return $other instanceof self && $this->name === $other->name;
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
