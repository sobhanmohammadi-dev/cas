<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

/**
 * Base type for every expression-tree node.
 *
 * Nodes are immutable value objects: transformations (simplification,
 * substitution, etc.) always return a new Node rather than mutating the
 * tree in place. This removes a whole class of aliasing bugs that the
 * mutable node tree in the previous design was prone to.
 */
abstract class Node
{
    public function __construct(
        public readonly int $startPos = 0,
        public readonly int $endPos = 0,
    ) {
    }

    abstract public function __toString(): string;

    /** Human/formatter-friendly rendering; numeric nodes override this. */
    public function toMathString(): string
    {
        return $this->__toString();
    }

    /** Structural equality, ignoring source position. */
    abstract public function equals(Node $other): bool;

    /** All direct child nodes, for generic tree walking. */
    abstract public function children(): array;

    /** Rebuild this node with new children (same arity/order as children()). */
    abstract public function withChildren(array $children): static;
}
