<?php
namespace CAS\Nodes;

use GMP;
use InvalidArgumentException;

class ComplexNode extends NumericNode {

    private GMP $real;
    private GMP $imag;

    public function __construct(string $realStr, string $imagStr, int $s, int $e) {

        parent::__construct($s, $e);

        if (!preg_match('/^[+-]?\d+$/', $realStr)) {throw new InvalidArgumentException('Invalid real part: ' . $realStr);}
        if (!preg_match('/^[+-]?\d+$/', $imagStr)) {throw new InvalidArgumentException('Invalid imaginary part: ' . $imagStr);}

        $this->real = \gmp_init($realStr);
        $this->imag = \gmp_init($imagStr);
    }

    public function toMathString(): string {
        $r = \gmp_strval($this->real);
        $i = \gmp_strval($this->imag);
        if (\gmp_cmp($this->imag, 0) >= 0) {
            return $r . '+' . $i . 'i';
        } else {
            return $r . $i . 'i';
        }
    }

    public function isZero(): bool {
        return \gmp_cmp($this->real, 0) === 0 && \gmp_cmp($this->imag, 0) === 0;
    }

    public function isOne(): bool {
        // 1 = 1+0i
        return \gmp_cmp($this->real, 1) === 0 && \gmp_cmp($this->imag, 0) === 0;
    }

    public function toInteger(): ?IntegerNode {
        if (\gmp_cmp($this->imag, 0) === 0) {
            return new IntegerNode(\gmp_strval($this->real), $this->getStartPos(), $this->getEndPos());
        }
        return null;
    }

    public function getReal(): GMP {
        return $this->real;
    }

    public function getImag(): GMP {
        return $this->imag;
    }

    public function getRealSign(): int {
        return \gmp_sign($this->real);
    }

    public function getImagSign(): int {
        return \gmp_sign($this->imag);
    }

    public function __toString(): string {
        $realStr = \gmp_strval($this->real);
        $imagStr = \gmp_strval($this->imag);

        if (\gmp_cmp($this->imag, 0) >= 0) {
            return $realStr . '+' . $imagStr . 'i';
        } else {
            return $realStr . $imagStr . 'i';
        }
    }
}