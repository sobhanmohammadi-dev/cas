<?php
namespace Sobhanmohammadi\CAS\Nodes;

use InvalidArgumentException;

class RationalNode extends NumericNode
{
    private \GMP $numerator;
    private \GMP $denominator;

    public function __construct(string $numerator, string $denominator, int $s, int $e)
    {
        parent::__construct($s, $e);

        if (!preg_match('/^[+-]?\d+$/', $numerator)) {
            throw new InvalidArgumentException('Invalid numerator: ' . $numerator);
        }
        if (!preg_match('/^[+-]?\d+$/', $denominator)) {
            throw new InvalidArgumentException('Invalid denominator: ' . $denominator);
        }

        $num = \gmp_init($numerator, 10);
        $den = \gmp_init($denominator, 10);

        if (\gmp_cmp($den, 0) === 0) {
            throw new InvalidArgumentException('Denominator cannot be zero.');
        }

        // Normalise: denominator always positive
        if (\gmp_sign($den) === -1) {
            $num = \gmp_neg($num);
            $den = \gmp_abs($den);
        }

        $this->numerator   = $num;
        $this->denominator = $den;
    }

    public function getValueOfNumerator(): \GMP   { return $this->numerator; }
    public function getValueOfDenominator(): \GMP { return $this->denominator; }
    public function getSignOfNumerator(): int      { return \gmp_sign($this->numerator); }

    public function isZero(): bool
    {
        return \gmp_cmp($this->numerator, 0) === 0;
    }

    public function isOne(): bool
    {
        return \gmp_cmp($this->numerator, 0) !== 0
            && \gmp_cmp($this->numerator, $this->denominator) === 0;
    }

    public function toInteger(): ?IntegerNode
    {
        if (\gmp_cmp(\gmp_mod($this->numerator, $this->denominator), 0) === 0) {
            return new IntegerNode(
                \gmp_strval(\gmp_div_q($this->numerator, $this->denominator)),
                $this->startPos,
                $this->endPos
            );
        }
        return null;
    }

    /** Returns "num/den" – consistent with toMathString(). */
    public function toMathString(): string
    {
        return \gmp_strval($this->numerator) . '/' . \gmp_strval($this->denominator);
    }

    public function __toString(): string
    {
        return $this->toMathString();
    }
}