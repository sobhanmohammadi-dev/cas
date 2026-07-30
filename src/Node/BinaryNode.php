<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

final class BinaryNode extends Node
{
    public function __construct(
        public readonly BinaryOperator $operator,
        public readonly Node $left,
        public readonly Node $right,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public function __toString(): string
    {
        return '(' . $this->left . ' ' . $this->operator->value . ' ' . $this->right . ')';
    }

    public function equals(Node $other): bool
    {
        return $other instanceof self
            && $this->operator === $other->operator
            && $this->left->equals($other->left)
            && $this->right->equals($other->right);
    }

    public function children(): array
    {
        return [$this->left, $this->right];
    }

    public function withChildren(array $children): static
    {
        [$left, $right] = $children;
        return new self($this->operator, $left, $right, $this->startPos, $this->endPos);
    }

    public function withOperands(Node $left, Node $right): self
    {
        return new self($this->operator, $left, $right, $this->startPos, $this->endPos);
    }
}
