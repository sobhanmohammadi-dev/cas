<?php
namespace CAS\Nodes;

abstract class NumericNode extends MathNode {
    abstract public function toMathString(): string;
    abstract public function isZero(): bool;
    abstract public function isOne(): bool;
    abstract public function toInteger(): ?IntegerNode;

    public static function fromDecimalString(string $raw, int $start, int $end): self
    {
        $sign = 1;
        if ($raw[0] === '+') {
            $raw = substr($raw, 1);
        } elseif ($raw[0] === '-') {
            $sign = -1;
            $raw = substr($raw, 1);
            if ($raw === '') { $raw = '0'; }
        }
        $raw = ltrim($raw, '0');
        if ($raw === '' || $raw[0] === '.') {
            $raw = '0' . $raw;
        }

        $ePos = strpos(strtolower($raw), 'e');
        $mantissa = $ePos !== false ? substr($raw, 0, $ePos) : $raw;
        $exponent = $ePos !== false ? (int)substr($raw, $ePos + 1) : 0;

        $dotPos = strpos($mantissa, '.');
        if ($dotPos === false) {
            $num = \gmp_init($mantissa);
            $den = \gmp_init(1);
        } else {
            $intPart = substr($mantissa, 0, $dotPos) ?: '0';
            $fracPart = substr($mantissa, $dotPos + 1);
            $num = \gmp_init($intPart . $fracPart);
            $den = \gmp_init('1' . str_repeat('0', strlen($fracPart)));
        }

        $ten = \gmp_init(10);
        if ($exponent > 0) {
            $num = \gmp_mul($num, \gmp_pow($ten, $exponent));
        } elseif ($exponent < 0) {
            $den = \gmp_mul($den, \gmp_pow($ten, -$exponent));
        }

        $gcd = \gmp_gcd($num, $den);
        $num = \gmp_div_q($num, $gcd);
        $den = \gmp_div_q($den, $gcd);

        if ($sign === -1) { $num = \gmp_neg($num); }

        if (\gmp_cmp($den, 1) === 0) {
            return new IntegerNode(\gmp_strval($num), $start, $end);
        }
        return new RationalNode(\gmp_strval($num), \gmp_strval($den), $start, $end);
    }
}