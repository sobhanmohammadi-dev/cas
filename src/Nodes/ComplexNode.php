<?php
namespace CAS\Nodes;

use GMP;
use InvalidArgumentException;

class ComplexNode extends NumericNode
{
    private GMP $real;
    private GMP $imag;

    public function __construct(string $realStr, string $imagStr, int $s, int $e)
    {
        parent::__construct($s, $e);

        if (!preg_match('/^[+-]?\d+$/', $realStr)) {
            throw new InvalidArgumentException('Invalid real part: ' . $realStr);
        }
        if (!preg_match('/^[+-]?\d+$/', $imagStr)) {
            throw new InvalidArgumentException('Invalid imaginary part: ' . $imagStr);
        }

        $this->real = \gmp_init($realStr);
        $this->imag = \gmp_init($imagStr);
    }

    public function getReal(): GMP { return $this->real; }
    public function getImag(): GMP { return $this->imag; }
    public function getRealSign(): int { return \gmp_sign($this->real); }
    public function getImagSign(): int { return \gmp_sign($this->imag); }

    public function isZero(): bool
    {
        return \gmp_cmp($this->real, 0) === 0 && \gmp_cmp($this->imag, 0) === 0;
    }

    public function isOne(): bool
    {
        return \gmp_cmp($this->real, 1) === 0 && \gmp_cmp($this->imag, 0) === 0;
    }

    public function toInteger(): ?IntegerNode
    {
        if (\gmp_cmp($this->imag, 0) === 0) {
            return new IntegerNode(\gmp_strval($this->real), $this->startPos, $this->endPos);
        }
        return null;
    }

    public function toMathString(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        $r = \gmp_strval($this->real);
        $i = \gmp_strval($this->imag);
        return \gmp_cmp($this->imag, 0) >= 0
            ? $r . '+' . $i . 'i'
            : $r . $i . 'i';
    }
}
