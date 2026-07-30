<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Evaluation;

use Sobhanmohammadi\CAS\Number\Rational;

/** Immutable-from-outside variable bindings used during evaluation. */
final class SymbolTable
{
    /** @param array<string, Rational> $bindings */
    public function __construct(private array $bindings = [])
    {
    }

    public function with(string $name, Rational $value): self
    {
        $copy = $this->bindings;
        $copy[$name] = $value;
        return new self($copy);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->bindings);
    }

    public function get(string $name): ?Rational
    {
        return $this->bindings[$name] ?? null;
    }
}
