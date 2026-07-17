<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\ComplexNode;

final class ComplexNodeTest extends TestCase
{
    public function testPositiveImaginaryPartStringification(): void
    {
        $n = new ComplexNode('3', '4', 0, 0);
        $this->assertSame('3+4i', (string) $n);
    }

    public function testNegativeImaginaryPartStringification(): void
    {
        $n = new ComplexNode('3', '-4', 0, 0);
        $this->assertSame('3-4i', (string) $n);
    }

    public function testIsZero(): void
    {
        $n = new ComplexNode('0', '0', 0, 0);
        $this->assertTrue($n->isZero());
    }

    public function testIsNotZeroWhenImaginaryPartNonzero(): void
    {
        $n = new ComplexNode('0', '1', 0, 0);
        $this->assertFalse($n->isZero());
    }

    public function testIsOne(): void
    {
        $n = new ComplexNode('1', '0', 0, 0);
        $this->assertTrue($n->isOne());
    }

    public function testToIntegerWhenImaginaryPartIsZero(): void
    {
        $n = new ComplexNode('5', '0', 0, 0);
        $int = $n->toInteger();
        $this->assertNotNull($int);
        $this->assertSame('5', (string) $int);
    }

    public function testToIntegerReturnsNullWhenImaginaryPartNonzero(): void
    {
        $n = new ComplexNode('5', '2', 0, 0);
        $this->assertNull($n->toInteger());
    }

    public function testInvalidRealPartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ComplexNode('x', '1', 0, 0);
    }

    public function testGetRealAndImagSigns(): void
    {
        $n = new ComplexNode('-3', '2', 0, 0);
        $this->assertSame(-1, $n->getRealSign());
        $this->assertSame(1, $n->getImagSign());
    }
}
