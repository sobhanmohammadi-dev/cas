<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Node;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\InvalidExpressionException;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\ConstantKind;
use Sobhanmohammadi\CAS\Node\ConstantNode;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\FunctionKind;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;

final class NodeTest extends TestCase
{
    public function testNumberNodeToString(): void
    {
        self::assertSame('5', (string) NumberNode::fromInt(5));
        self::assertSame('1/2', (string) NumberNode::fromDecimalString('0.5'));
    }

    public function testNumberNodePredicates(): void
    {
        self::assertTrue(NumberNode::fromInt(0)->isZero());
        self::assertTrue(NumberNode::fromInt(1)->isOne());
        self::assertTrue(NumberNode::fromInt(-1)->isNegative());
    }

    public function testVariableNodeEquality(): void
    {
        self::assertTrue((new VariableNode('x'))->equals(new VariableNode('x')));
        self::assertFalse((new VariableNode('x'))->equals(new VariableNode('y')));
    }

    public function testBinaryNodeToStringAndChildren(): void
    {
        $node = new BinaryNode(BinaryOperator::Add, NumberNode::fromInt(1), NumberNode::fromInt(2));
        self::assertSame('(1 + 2)', (string) $node);
        self::assertCount(2, $node->children());
    }

    public function testBinaryNodeWithChildrenRebuilds(): void
    {
        $node = new BinaryNode(BinaryOperator::Multiply, NumberNode::fromInt(2), NumberNode::fromInt(3));
        $rebuilt = $node->withChildren([NumberNode::fromInt(5), NumberNode::fromInt(6)]);
        self::assertSame('(5 * 6)', (string) $rebuilt);
        self::assertSame(BinaryOperator::Multiply, $rebuilt->operator);
    }

    public function testNegateNodeToStringAndEquality(): void
    {
        $a = new NegateNode(new VariableNode('x'));
        $b = new NegateNode(new VariableNode('x'));
        self::assertSame('(-x)', (string) $a);
        self::assertTrue($a->equals($b));
    }

    public function testConstantNode(): void
    {
        $pi = new ConstantNode(ConstantKind::Pi);
        self::assertSame('pi', (string) $pi);
        self::assertEqualsWithDelta(M_PI, $pi->kind->approximateValue(), 1e-12);
    }

    public function testFunctionNodeArityIsEnforced(): void
    {
        $this->expectException(InvalidExpressionException::class);
        new FunctionNode(FunctionKind::Sin, [NumberNode::fromInt(1), NumberNode::fromInt(2)]);
    }

    public function testFunctionNodeToString(): void
    {
        $node = new FunctionNode(FunctionKind::Atan2, [NumberNode::fromInt(1), NumberNode::fromInt(2)]);
        self::assertSame('atan2(1, 2)', (string) $node);
    }

    public function testFunctionNodeEquality(): void
    {
        $a = new FunctionNode(FunctionKind::Sqrt, [NumberNode::fromInt(4)]);
        $b = new FunctionNode(FunctionKind::Sqrt, [NumberNode::fromInt(4)]);
        $c = new FunctionNode(FunctionKind::Sqrt, [NumberNode::fromInt(9)]);
        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testEquationNodeToString(): void
    {
        $eq = new EquationNode(new VariableNode('x'), NumberNode::fromInt(5));
        self::assertSame('x = 5', (string) $eq);
    }

    public function testFunctionKindArity(): void
    {
        self::assertSame(1, FunctionKind::Sin->arity());
        self::assertSame(2, FunctionKind::Atan2->arity());
        self::assertSame(2, FunctionKind::Root->arity());
    }

    public function testBinaryOperatorPrecedence(): void
    {
        self::assertTrue(BinaryOperator::Multiply->precedence() > BinaryOperator::Add->precedence());
        self::assertTrue(BinaryOperator::Power->precedence() > BinaryOperator::Multiply->precedence());
        self::assertTrue(BinaryOperator::Power->isRightAssociative());
        self::assertFalse(BinaryOperator::Add->isRightAssociative());
    }
}
