<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\IntegerNode;

final class IntegerNodeTest extends TestCase
{
    public function testStoresValueAndStringifies(): void
    {
        $n = new IntegerNode('42', 0, 2);
        $this->assertSame('42', (string) $n);
        $this->assertSame('42', $n->toMathString());
        $this->assertSame(0, \gmp_cmp($n->getValue(), 42));
    }

    public function testNegativeSign(): void
    {
        $n = new IntegerNode('-5', 0, 2);
        $this->assertSame(-1, $n->getSign());
        $this->assertSame('-5', (string) $n);
    }

    public function testZeroSign(): void
    {
        $n = new IntegerNode('0', 0, 1);
        $this->assertSame(0, $n->getSign());
        $this->assertTrue($n->isZero());
        $this->assertFalse($n->isOne());
    }

    public function testIsOne(): void
    {
        $n = new IntegerNode('1', 0, 1);
        $this->assertTrue($n->isOne());
        $this->assertFalse($n->isZero());
    }

    public function testToIntegerReturnsSelf(): void
    {
        $n = new IntegerNode('9', 0, 1);
        $this->assertSame($n, $n->toInteger());
    }

    public function testInvalidStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new IntegerNode('not-a-number', 0, 0);
    }

    public function testHandlesArbitrarilyLargeIntegers(): void
    {
        // Exercises GMP's arbitrary-precision behaviour beyond native int range.
        $big = '123456789012345678901234567890';
        $n = new IntegerNode($big, 0, 0);
        $this->assertSame($big, (string) $n);
    }
}
