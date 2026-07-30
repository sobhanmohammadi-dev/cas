<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Number;

use GMP;
use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Exception\DomainException;

/**
 * An immutable, exact rational number backed by GMP.
 *
 * This is the single numeric value type used throughout the library,
 * replacing the old IntegerNode/RationalNode split. A Rational always
 * carries an integer denominator; integers are simply Rationals with
 * denominator 1 (see isInteger()).
 */
final class Rational
{
    private readonly GMP $numerator;
    private readonly GMP $denominator;

    private function __construct(GMP $numerator, GMP $denominator)
    {
        $this->numerator = $numerator;
        $this->denominator = $denominator;
    }

    public static function fromInt(int $value): self
    {
        return new self(gmp_init($value), gmp_init(1));
    }

    public static function fromGmp(GMP $numerator, ?GMP $denominator = null): self
    {
        return self::normalize($numerator, $denominator ?? gmp_init(1));
    }

    public static function fromIntStrings(string $numerator, string $denominator = '1'): self
    {
        self::assertIntegerString($numerator, 'numerator');
        self::assertIntegerString($denominator, 'denominator');

        return self::normalize(gmp_init($numerator, 10), gmp_init($denominator, 10));
    }

    /**
     * Parse a base-10 decimal or scientific-notation literal (e.g. "3.14",
     * "-.5", "2e10") into an exact Rational. Never uses floating point.
     */
    public static function fromDecimalString(string $raw): self
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new DomainException('Cannot parse an empty numeric literal.');
        }

        $sign = 1;
        if ($raw[0] === '+' || $raw[0] === '-') {
            $sign = $raw[0] === '-' ? -1 : 1;
            $raw = substr($raw, 1);
        }

        if ($raw === '' || $raw === '.') {
            $raw = '0';
        }
        if ($raw[0] === '.') {
            $raw = '0' . $raw;
        }

        $ePos = stripos($raw, 'e');
        $mantissa = $ePos !== false ? substr($raw, 0, $ePos) : $raw;
        $exponent = $ePos !== false ? (int) substr($raw, $ePos + 1) : 0;

        $dotPos = strpos($mantissa, '.');
        if ($dotPos === false) {
            $numString = $mantissa === '' ? '0' : $mantissa;
            $fracDigits = 0;
        } else {
            $intPart = substr($mantissa, 0, $dotPos);
            $fracPart = substr($mantissa, $dotPos + 1);
            $numString = ltrim($intPart . $fracPart, '0');
            $numString = $numString === '' ? '0' : $numString;
            $fracDigits = strlen($fracPart);
        }

        // gmp_init requires an explicit base; without it, strings like "070"
        // are misread as octal. Always pass base 10 here.
        $num = gmp_init($numString, 10);
        $den = gmp_init('1' . str_repeat('0', $fracDigits), 10);

        if ($exponent > 0) {
            $num = gmp_mul($num, gmp_pow(gmp_init(10), $exponent));
        } elseif ($exponent < 0) {
            $den = gmp_mul($den, gmp_pow(gmp_init(10), -$exponent));
        }

        if ($sign === -1) {
            $num = gmp_neg($num);
        }

        return self::normalize($num, $den);
    }

    private static function normalize(GMP $num, GMP $den): self
    {
        if (gmp_sign($den) === 0) {
            throw new DivisionByZeroException('Rational denominator cannot be zero.');
        }
        if (gmp_sign($den) === -1) {
            $num = gmp_neg($num);
            $den = gmp_neg($den);
        }
        if (gmp_sign($num) === 0) {
            return new self(gmp_init(0), gmp_init(1));
        }
        $gcd = gmp_gcd(gmp_abs($num), $den);
        if (gmp_cmp($gcd, 1) !== 0) {
            $num = gmp_div_q($num, $gcd);
            $den = gmp_div_q($den, $gcd);
        }
        return new self($num, $den);
    }

    private static function assertIntegerString(string $value, string $label): void
    {
        if (!preg_match('/^[+-]?\d+$/', $value)) {
            throw new DomainException("Invalid integer string for {$label}: {$value}");
        }
    }

    public function numerator(): GMP
    {
        return $this->numerator;
    }

    public function denominator(): GMP
    {
        return $this->denominator;
    }

    public function isInteger(): bool
    {
        return gmp_cmp($this->denominator, 1) === 0;
    }

    public function isZero(): bool
    {
        return gmp_sign($this->numerator) === 0;
    }

    public function isOne(): bool
    {
        return $this->isInteger() && gmp_cmp($this->numerator, 1) === 0;
    }

    public function isNegative(): bool
    {
        return gmp_sign($this->numerator) === -1;
    }

    public function sign(): int
    {
        return gmp_sign($this->numerator);
    }

    public function add(self $other): self
    {
        return self::normalize(
            gmp_add(
                gmp_mul($this->numerator, $other->denominator),
                gmp_mul($other->numerator, $this->denominator)
            ),
            gmp_mul($this->denominator, $other->denominator)
        );
    }

    public function sub(self $other): self
    {
        return $this->add($other->negate());
    }

    public function negate(): self
    {
        return new self(gmp_neg($this->numerator), $this->denominator);
    }

    public function mul(self $other): self
    {
        return self::normalize(
            gmp_mul($this->numerator, $other->numerator),
            gmp_mul($this->denominator, $other->denominator)
        );
    }

    public function div(self $other): self
    {
        if ($other->isZero()) {
            throw new DivisionByZeroException('Division by zero.');
        }
        return self::normalize(
            gmp_mul($this->numerator, $other->denominator),
            gmp_mul($this->denominator, $other->numerator)
        );
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    public function reciprocal(): self
    {
        if ($this->isZero()) {
            throw new DivisionByZeroException('Cannot take the reciprocal of zero.');
        }
        return self::normalize($this->denominator, $this->numerator);
    }

    /**
     * Raise this rational to an integer power (positive, negative or zero).
     */
    public function pow(int $exponent): self
    {
        if ($exponent === 0) {
            if ($this->isZero()) {
                throw new DivisionByZeroException('0^0 is undefined.');
            }
            return self::fromInt(1);
        }
        if ($exponent < 0) {
            if ($this->isZero()) {
                throw new DivisionByZeroException('Cannot raise 0 to a negative power.');
            }
            return $this->reciprocal()->pow(-$exponent);
        }
        return self::normalize(
            gmp_pow($this->numerator, $exponent),
            gmp_pow($this->denominator, $exponent)
        );
    }

    public function compareTo(self $other): int
    {
        return gmp_cmp(
            gmp_mul($this->numerator, $other->denominator),
            gmp_mul($other->numerator, $this->denominator)
        );
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function toInt(): int
    {
        if (!$this->isInteger()) {
            throw new DomainException('Rational is not an integer.');
        }
        return gmp_intval($this->numerator);
    }

    /**
     * Exact nth root via Newton's method on GMP integers, returning null
     * if the root is not exact. bcpow() cannot be trusted with fractional
     * exponents (silent truncation / ValueError across PHP versions), so
     * all root-finding here is done with integer Newton iteration.
     */
    public function exactNthRoot(int $n): ?self
    {
        if ($n <= 0) {
            throw new DomainException('Root degree must be positive.');
        }
        if ($this->isZero()) {
            return self::fromInt(0);
        }
        if ($this->isNegative() && $n % 2 === 0) {
            return null;
        }

        $numRoot = self::gmpExactRoot(gmp_abs($this->numerator), $n);
        $denRoot = self::gmpExactRoot($this->denominator, $n);
        if ($numRoot === null || $denRoot === null) {
            return null;
        }

        $result = self::normalize($numRoot, $denRoot);
        return $this->isNegative() ? $result->negate() : $result;
    }

    private static function gmpExactRoot(GMP $value, int $n): ?GMP
    {
        [$root, $remainder] = gmp_rootrem($value, $n);
        return gmp_sign($remainder) === 0 ? $root : null;
    }

    public function toDecimalString(int $scale = 10): string
    {
        if ($this->isInteger()) {
            return gmp_strval($this->numerator);
        }
        return bcdiv(gmp_strval($this->numerator), gmp_strval($this->denominator), $scale);
    }

    public function toMathString(): string
    {
        return $this->isInteger()
            ? gmp_strval($this->numerator)
            : gmp_strval($this->numerator) . '/' . gmp_strval($this->denominator);
    }

    public function __toString(): string
    {
        return $this->toMathString();
    }
}
