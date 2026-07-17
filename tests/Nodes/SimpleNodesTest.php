<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\VariableNode;
use Sobhanmohammadi\CAS\Nodes\PiNode;
use Sobhanmohammadi\CAS\Nodes\UnaryNode;
use Sobhanmohammadi\CAS\Nodes\IntegerNode;
use Sobhanmohammadi\CAS\Nodes\EquationNode;
use Sobhanmohammadi\CAS\Nodes\AssignmentNode;

final class SimpleNodesTest extends TestCase
{
    public function testVariableNode(): void
    {
        $v = new VariableNode('x', 0, 1);
        $this->assertSame('x', $v->getName());
        $this->assertSame('x', (string) $v);
        $this->assertSame('x', $v->toMathString());
    }

    public function testPiNode(): void
    {
        $p = new PiNode(0, 1);
        $this->assertSame('pi', $p->getConstantName());
        $this->assertSame('π', (string) $p);
    }

    public function testUnaryNode(): void
    {
        $inner = new IntegerNode('5', 0, 1);
        $u = new UnaryNode('-', $inner, 0, 2);
        $this->assertSame('-', $u->getOp());
        $this->assertSame($inner, $u->getOperand());
        $this->assertSame('-(5)', (string) $u);
    }

    public function testEquationNode(): void
    {
        $l = new VariableNode('x', 0, 1);
        $r = new IntegerNode('5', 2, 3);
        $eq = new EquationNode($l, $r, 0, 3);
        $this->assertSame($l, $eq->getLeft());
        $this->assertSame($r, $eq->getRight());
        $this->assertSame('x = 5', (string) $eq);
    }

    public function testAssignmentNode(): void
    {
        $expr = new IntegerNode('7', 0, 1);
        $a = new AssignmentNode('y', $expr, 0, 5);
        $this->assertSame('y', $a->getVariableName());
        $this->assertSame($expr, $a->getExpression());
        $this->assertSame('y = 7', (string) $a);
    }
}
