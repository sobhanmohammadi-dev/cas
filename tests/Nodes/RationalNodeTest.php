<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\RationalNode;

final class RationalNodeTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $n = new RationalNode('3', '4', 0, 0);
        $this->assertSame('3/4', (string) $n);
        $this->assertSame('3/4', $n->toMathString());
    }

    public function testNegativeDenominatorIsNormalised(): void
    {
        // 3/-4 should normalise to -3/4 (denominator always positive).
        $n = new RationalNode('3', '-4', 0, 0);
        $this->assertSame('-3/4', (string) $n);
        $this->assertSame(-1, $n->getSignOfNumerator());
    }

    public function testZeroDenominatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RationalNode('1', '0', 0, 0);
    }

    public function testInvalidNumeratorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RationalNode('x', '2', 0, 0);
    }

    public function testInvalidDenominatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RationalNode('1', 'x', 0, 0);
    }

    public function testIsZero(): void
    {
        $n = new RationalNode('0', '5', 0, 0);
        $this->assertTrue($n->isZero());
    }

    public function testIsOneWhenNumeratorEqualsDenominator(): void
    {
        $n = new RationalNode('5', '5', 0, 0);
        $this->assertTrue($n->isOne());
    }

    public function testIsOneFalseForZeroOverZeroLikeCases(): void
    {
        // Numerator 0 must never be reported as "one", even if denominator also matches trivially.
        $n = new RationalNode('0', '1', 0, 0);
        $this->assertFalse($n->isOne());
    }

    public function testToIntegerWhenExactlyDivisible(): void
    {
        $n = new RationalNode('8', '4', 0, 0);
        $int = $n->toInteger();
        $this->assertNotNull($int);
        $this->assertSame('2', (string) $int);
    }

    public function testToIntegerReturnsNullWhenNotDivisible(): void
    {
        $n = new RationalNode('3', '4', 0, 0);
        $this->assertNull($n->toInteger());
    }
}
