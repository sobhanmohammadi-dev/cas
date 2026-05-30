<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\MathNode;

class SymbolTable
{
    /** @var MathNode[] */
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

    /** @return MathNode[] */
    public function all(): array
    {
        return $this->symbols;
    }
}
