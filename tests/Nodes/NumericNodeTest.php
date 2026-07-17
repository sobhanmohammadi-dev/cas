<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\NumericNode;
use Sobhanmohammadi\CAS\Nodes\IntegerNode;
use Sobhanmohammadi\CAS\Nodes\RationalNode;

final class NumericNodeTest extends TestCase
{
    public function testPlainIntegerString(): void
    {
        $n = NumericNode::fromDecimalString('42', 0, 2);
        $this->assertInstanceOf(IntegerNode::class, $n);
        $this->assertSame('42', (string) $n);
    }

    public function testDecimalStringReducesToRational(): void
    {
        $n = NumericNode::fromDecimalString('0.5', 0, 3);
        $this->assertInstanceOf(RationalNode::class, $n);
        $this->assertSame('1/2', (string) $n);
    }

    public function testDecimalThatReducesToIntegerBecomesIntegerNode(): void
    {
        // 2.0 has no fractional value once reduced -> should be IntegerNode, not 20/10.
        $n = NumericNode::fromDecimalString('2.0', 0, 3);
        $this->assertInstanceOf(IntegerNode::class, $n);
        $this->assertSame('2', (string) $n);
    }

    public function testLeadingZerosAreStripped(): void
    {
        $n = NumericNode::fromDecimalString('007', 0, 3);
        $this->assertSame('7', (string) $n);
    }

    public function testLeadingDotIsHandled(): void
    {
        $n = NumericNode::fromDecimalString('.25', 0, 3);
        $this->assertSame('1/4', (string) $n);
    }

    public function testNegativeSign(): void
    {
        $n = NumericNode::fromDecimalString('-3', 0, 2);
        $this->assertSame('-3', (string) $n);
    }

    public function testPositiveSignPrefix(): void
    {
        $n = NumericNode::fromDecimalString('+3', 0, 2);
        $this->assertSame('3', (string) $n);
    }

    public function testScientificNotationPositiveExponent(): void
    {
        $n = NumericNode::fromDecimalString('1.5e2', 0, 5);
        $this->assertInstanceOf(IntegerNode::class, $n);
        $this->assertSame('150', (string) $n);
    }

    public function testScientificNotationNegativeExponent(): void
    {
        $n = NumericNode::fromDecimalString('5e-1', 0, 5);
        $this->assertSame('1/2', (string) $n);
    }

    public function testFractionIsFullyReduced(): void
    {
        $n = NumericNode::fromDecimalString('0.100', 0, 5); // 100/1000 -> 1/10
        $this->assertSame('1/10', (string) $n);
    }
}
