<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Evaluation;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Exception\UnboundVariableException;
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Parsing\Parser;

final class ExactEvaluatorTest extends TestCase
{
    private Parser $parser;
    private ExactEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->evaluator = new ExactEvaluator();
    }

    private function eval(string $expr, SymbolTable $symbols = new SymbolTable()): Rational
    {
        return $this->evaluator->evaluate($this->parser->parse($expr), $symbols);
    }

    public function testBasicArithmetic(): void
    {
        self::assertSame('14', (string) $this->eval('2 + 3 * 4'));
        self::assertSame('1/2', (string) $this->eval('1/2'));
    }

    public function testIntegerPower(): void
    {
        self::assertSame('8', (string) $this->eval('2^3'));
        self::assertSame('1/8', (string) $this->eval('2^-3'));
    }

    public function testVariableSubstitution(): void
    {
        $symbols = (new SymbolTable())->with('x', Rational::fromInt(5));
        self::assertSame('11', (string) $this->eval('2*x + 1', $symbols));
    }

    public function testUnboundVariableThrows(): void
    {
        $this->expectException(UnboundVariableException::class);
        $this->eval('x + 1');
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        $this->eval('1/0');
    }

    public function testTranscendentalThrowsUnsupported(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->eval('sin(1)');
    }

    public function testNonIntegerExponentThrowsUnsupported(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->eval('2^(1/2)');
    }

    public function testNegatedIntegerExponentIsAllowed(): void
    {
        // Regression: unary minus produces a NegateNode wrapping the
        // literal, not a negative NumberNode, so the exponent must be
        // evaluated rather than pattern-matched.
        self::assertSame('1/8', (string) $this->eval('2^-3'));
    }
}
