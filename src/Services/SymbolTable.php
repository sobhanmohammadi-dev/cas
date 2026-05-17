<?php
namespace CAS\Services;

use CAS\Nodes\MathNode;

class SymbolTable
{
    private array $symbols = [];

    public function assign(string $name, MathNode $value): void
    {
        $this->symbols[$name] = $value;
    }

    public function lookup(string $name): ?MathNode
    {
        return $this->symbols[$name] ?? null;
    }

    public function isAssigned(string $name): bool
    {
        return array_key_exists($name, $this->symbols);
    }

    public function remove(string $name): void
    {
        unset($this->symbols[$name]);
    }

    public function all(): array
    {
        return $this->symbols;
    }
}