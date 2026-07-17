<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\{SymbolTable, NumericEvaluator};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Nodes\{IntegerNode, RationalNode, ComplexNode};

final class NumericEvaluatorTest extends TestCase
{
    private function eval(string $src, ?SymbolTable $sym = null)
    {
        $sym = $sym ?? new SymbolTable();
        $ev  = new NumericEvaluator($sym);
        $tokens = (new Lexer($src))->tokenize();
        $ast = (new Parser($tokens, $src))->parse();
        return $ev->evaluate($ast);
    }

    public function testBasicArithmetic(): void
    {
        $this->assertSame('7', (string) $this->eval('3 + 4'));
        $this->assertSame('12', (string) $this->eval('3 * 4'));
        $this->assertSame('1/2', (string) $this->eval('1 / 2'));
        $this->assertSame('8', (string) $this->eval('2^3'));
    }

    public function testNegativeExponent(): void
    {
        $this->assertSame('1/4', (string) $this->eval('2^(-2)'));
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('5 / 0');
    }

    public function testSqrtOfPerfectSquare(): void
    {
        $this->assertSame('7', (string) $this->eval('sqrt(49)'));
    }

    public function testSqrtOfNonPerfectSquareThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('sqrt(2)');
    }

    public function testSqrtOfNegativeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('sqrt(-4)');
    }

    public function testExactNthRoot(): void
    {
        $this->assertSame('3', (string) $this->eval('radical(3, 27)'));
    }

    public function testEvenRootOfNegativeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('radical(2, -4)');
    }

    public function testPiThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('pi');
    }

    public function testVariableLookup(): void
    {
        $sym = new SymbolTable();
        $sym->assign('x', new IntegerNode('5', 0, 1));
        $this->assertSame('10', (string) $this->eval('2 * x', $sym));
    }

    public function testUndefinedVariableThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('x + 1');
    }

    public function testUnaryMinus(): void
    {
        $this->assertSame('-5', (string) $this->eval('-5'));
    }

    public function testRationalReduction(): void
    {
        $this->assertSame('1/2', (string) $this->eval('2 / 4'));
    }

    public function testExponentMustBeInteger(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->eval('2^(1/2)');
    }
}
