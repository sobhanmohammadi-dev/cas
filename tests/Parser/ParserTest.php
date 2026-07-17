<?php
namespace Sobhanmohammadi\CAS\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Nodes\{
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, IntegerNode, VariableNode, PiNode, SqrtNode, RootNode, EquationNode
};
use Sobhanmohammadi\CAS\Exception\MathParseException;

final class ParserTest extends TestCase
{
    private function parse(string $src)
    {
        $tokens = (new Lexer($src))->tokenize();
        return (new Parser($tokens, $src))->parse();
    }

    public function testOperatorPrecedence(): void
    {
        // 2 + 3 * 4 -> Plus(2, Multiply(3,4))
        $node = $this->parse('2 + 3 * 4');
        $this->assertInstanceOf(PlusNode::class, $node);
        $this->assertInstanceOf(IntegerNode::class, $node->getLeft());
        $this->assertInstanceOf(MultiplyNode::class, $node->getRight());
    }

    public function testParenthesesOverridePrecedence(): void
    {
        $node = $this->parse('(2 + 3) * 4');
        $this->assertInstanceOf(MultiplyNode::class, $node);
        $this->assertInstanceOf(PlusNode::class, $node->getLeft());
    }

    public function testPowerIsRightAssociative(): void
    {
        // 2^3^2 -> 2^(3^2), not (2^3)^2
        $node = $this->parse('2^3^2');
        $this->assertInstanceOf(PowerNode::class, $node);
        $this->assertInstanceOf(IntegerNode::class, $node->getLeft());
        $this->assertInstanceOf(PowerNode::class, $node->getRight());
    }

    public function testUnaryMinus(): void
    {
        $node = $this->parse('-5');
        $this->assertInstanceOf(UnaryNode::class, $node);
        $this->assertSame('-', $node->getOp());
    }

    public function testUnaryPlusIsAbsorbed(): void
    {
        $node = $this->parse('+5');
        $this->assertInstanceOf(IntegerNode::class, $node);
    }

    public function testDoubleUnaryMinus(): void
    {
        $node = $this->parse('--5');
        $this->assertInstanceOf(UnaryNode::class, $node);
        $this->assertInstanceOf(UnaryNode::class, $node->getOperand());
    }

    public function testImplicitMultiplicationNumberVariable(): void
    {
        // 2x -> Multiply(2, x)
        $node = $this->parse('2x');
        $this->assertInstanceOf(MultiplyNode::class, $node);
        $this->assertInstanceOf(IntegerNode::class, $node->getLeft());
        $this->assertInstanceOf(VariableNode::class, $node->getRight());
    }

    public function testImplicitMultiplicationWithParens(): void
    {
        // 2(3+4) -> Multiply(2, (3+4))
        $node = $this->parse('2(3+4)');
        $this->assertInstanceOf(MultiplyNode::class, $node);
        $this->assertInstanceOf(PlusNode::class, $node->getRight());
    }

    public function testChainedImplicitMultiplication(): void
    {
        // 2 3 4 -> ((2*3)*4)
        $node = $this->parse('2 3 4');
        $this->assertInstanceOf(MultiplyNode::class, $node);
        $this->assertInstanceOf(MultiplyNode::class, $node->getLeft());
    }

    public function testPiLiteral(): void
    {
        $node = $this->parse('pi');
        $this->assertInstanceOf(PiNode::class, $node);
    }

    public function testSqrtFunction(): void
    {
        $node = $this->parse('sqrt(9)');
        $this->assertInstanceOf(SqrtNode::class, $node);
        $this->assertInstanceOf(IntegerNode::class, $node->getRadicand());
    }

    public function testRadicalFunctionDegreeThenRadicand(): void
    {
        $node = $this->parse('radical(3, 27)');
        $this->assertInstanceOf(RootNode::class, $node);
        $this->assertSame('3', (string) $node->getDegree());
        $this->assertSame('27', (string) $node->getRadicand());
    }

    public function testEmptyParenthesesThrows(): void
    {
        $this->expectException(MathParseException::class);
        $this->parse('()');
    }

    public function testUnexpectedTrailingTokenThrows(): void
    {
        $this->expectException(MathParseException::class);
        $this->parse('2 + 3)');
    }

    public function testMissingClosingParenThrows(): void
    {
        $this->expectException(MathParseException::class);
        $this->parse('(2 + 3');
    }

    public function testParseEquation(): void
    {
        $tokens = (new Lexer('2*x + 1 = 7'))->tokenize();
        $eq = (new Parser($tokens, '2*x + 1 = 7'))->parseEquation();
        $this->assertInstanceOf(EquationNode::class, $eq);
        $this->assertInstanceOf(PlusNode::class, $eq->getLeft());
        $this->assertInstanceOf(IntegerNode::class, $eq->getRight());
    }

    public function testParseEquationRequiresEquals(): void
    {
        $this->expectException(MathParseException::class);
        $tokens = (new Lexer('2 + 3'))->tokenize();
        (new Parser($tokens, '2 + 3'))->parseEquation();
    }

    public function testDeeplyNestedParenthesesTriggersDepthGuard(): void
    {
        $src = str_repeat('(', 250) . '1' . str_repeat(')', 250);
        $this->expectException(MathParseException::class);
        $this->parse($src);
    }
}
