<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Number;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Number\Rational;

final class RationalTest extends TestCase
{
    public function testFromIntRoundTrips(): void
    {
        self::assertSame('5', (string) Rational::fromInt(5));
    }

    public function testAdditionReducesToLowestTerms(): void
    {
        $sum = Rational::fromIntStrings('1', '3')->add(Rational::fromIntStrings('1', '6'));
        self::assertSame('1/2', $sum->toMathString());
    }

    public function testSubtractionAndNegation(): void
    {
        $result = Rational::fromInt(3)->sub(Rational::fromInt(5));
        self::assertSame('-2', (string) $result);
    }

    public function testMultiplicationAndDivision(): void
    {
        self::assertSame('6', (string) Rational::fromInt(2)->mul(Rational::fromInt(3)));
        self::assertSame('1/2', (string) Rational::fromInt(1)->div(Rational::fromInt(2)));
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        Rational::fromInt(1)->div(Rational::fromInt(0));
    }

    public function testNegativePowerOfZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        Rational::fromInt(0)->pow(-1);
    }

    public function testZeroToTheZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        Rational::fromInt(0)->pow(0);
    }

    public function testPowNegativeExponent(): void
    {
        self::assertSame('1/4', (string) Rational::fromInt(2)->pow(-2));
    }

    public function testFromDecimalStringHandlesLeadingDot(): void
    {
        self::assertSame('-1/2', (string) Rational::fromDecimalString('-.5'));
    }

    public function testFromDecimalStringHandlesLeadingZeroBelowOne(): void
    {
        // Regression test: gmp_init() without an explicit base previously
        // misread "0.070..." style fractional digits as octal.
        self::assertSame('7/100', (string) Rational::fromDecimalString('0.07'));
    }

    public function testFromDecimalStringHandlesScientificNotation(): void
    {
        self::assertSame('2000', (string) Rational::fromDecimalString('2e3'));
        self::assertSame('1/1000', (string) Rational::fromDecimalString('1e-3'));
    }

    public function testExactNthRootOfPerfectSquare(): void
    {
        self::assertSame('3', (string) Rational::fromInt(9)->exactNthRoot(2));
    }

    public function testExactNthRootReturnsNullWhenInexact(): void
    {
        self::assertNull(Rational::fromInt(8)->exactNthRoot(2));
    }

    public function testExactNthRootOfNegativeOddDegree(): void
    {
        self::assertSame('-2', (string) Rational::fromInt(-8)->exactNthRoot(3));
    }

    public function testExactNthRootOfNegativeEvenDegreeIsNull(): void
    {
        self::assertNull(Rational::fromInt(-4)->exactNthRoot(2));
    }

    public function testCompareToAndEquals(): void
    {
        self::assertTrue(Rational::fromIntStrings('2', '4')->equals(Rational::fromIntStrings('1', '2')));
        self::assertSame(-1, Rational::fromInt(1)->compareTo(Rational::fromInt(2)));
    }

    public function testNormalizesNegativeDenominator(): void
    {
        self::assertSame('-1/2', (string) Rational::fromIntStrings('1', '-2'));
    }
}
