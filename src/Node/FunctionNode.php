<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

use Sobhanmohammadi\CAS\Exception\InvalidExpressionException;

final class FunctionNode extends Node
{
    /** @param Node[] $arguments */
    public function __construct(
        public readonly FunctionKind $kind,
        public readonly array $arguments,
        int $startPos = 0,
        int $endPos = 0,
    ) {
        if (count($arguments) !== $kind->arity()) {
            throw new InvalidExpressionException(sprintf(
                '%s expects %d argument(s), got %d.',
                $kind->value,
                $kind->arity(),
                count($arguments)
            ));
        }
        parent::__construct($startPos, $endPos);
    }

    public function __toString(): string
    {
        return $this->kind->value . '(' . implode(', ', array_map(strval(...), $this->arguments)) . ')';
    }

    public function equals(Node $other): bool
    {
        if (!$other instanceof self || $this->kind !== $other->kind || count($this->arguments) !== count($other->arguments)) {
            return false;
        }
        foreach ($this->arguments as $i => $arg) {
            if (!$arg->equals($other->arguments[$i])) {
                return false;
            }
        }
        return true;
    }

    public function children(): array
    {
        return $this->arguments;
    }

    public function withChildren(array $children): static
    {
        return new self($this->kind, array_values($children), $this->startPos, $this->endPos);
    }
}
