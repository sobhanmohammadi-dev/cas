<?php
namespace Sobhanmohammadi\CAS\Nodes;

use InvalidArgumentException;

class IntegerNode extends NumericNode
{
    private \GMP $value;

    public function __construct(string $numericStr, int $s, int $e)
    {
        parent::__construct($s, $e);
        if (!preg_match('/^[+-]?\d+$/', $numericStr)) {
            throw new InvalidArgumentException('Invalid integer string: ' . $numericStr);
        }
        $this->value = \gmp_init($numericStr, 10);
    }

    public function getValue(): \GMP         { return $this->value; }
    public function getSign(): int           { return \gmp_sign($this->value); }
    public function isZero(): bool           { return \gmp_cmp($this->value, 0) === 0; }
    public function isOne(): bool            { return \gmp_cmp($this->value, 1) === 0; }
    public function toInteger(): IntegerNode { return $this; }
    public function toMathString(): string   { return \gmp_strval($this->value); }
    public function __toString(): string     { return \gmp_strval($this->value); }
}
