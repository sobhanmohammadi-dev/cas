<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\{
    IntegerNode, PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode, BinaryOperatorNode
};

final class BinaryOperatorNodesTest extends TestCase
{
    private function pair(): array
    {
        return [new IntegerNode('2', 0, 1), new IntegerNode('3', 2, 3)];
    }

    public function testPlusNode(): void
    {
        [$l, $r] = $this->pair();
        $n = new PlusNode($l, $r, 0, 3);
        $this->assertInstanceOf(BinaryOperatorNode::class, $n);
        $this->assertSame('+', $n->getOperatorSymbol());
        $this->assertSame('(2 + 3)', (string) $n);
        $this->assertSame($l, $n->getLeft());
        $this->assertSame($r, $n->getRight());
    }

    public function testMinusNode(): void
    {
        [$l, $r] = $this->pair();
        $n = new MinusNode($l, $r, 0, 3);
        $this->assertSame('-', $n->getOperatorSymbol());
        $this->assertSame('(2 - 3)', (string) $n);
    }

    public function testMultiplyNode(): void
    {
        [$l, $r] = $this->pair();
        $n = new MultiplyNode($l, $r, 0, 3);
        $this->assertSame('*', $n->getOperatorSymbol());
        $this->assertSame('(2 * 3)', (string) $n);
    }

    public function testDivideNode(): void
    {
        [$l, $r] = $this->pair();
        $n = new DivideNode($l, $r, 0, 3);
        $this->assertSame('/', $n->getOperatorSymbol());
        $this->assertSame('(2 / 3)', (string) $n);
    }

    public function testPowerNode(): void
    {
        [$l, $r] = $this->pair();
        $n = new PowerNode($l, $r, 0, 3);
        $this->assertSame('^', $n->getOperatorSymbol());
        $this->assertSame('(2 ^ 3)', (string) $n);
    }
}
