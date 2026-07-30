<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Evaluation;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Evaluation\NumericEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Exception\DivisionByZeroException;
use Sobhanmohammadi\CAS\Exception\DomainException;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Parsing\Parser;

final class NumericEvaluatorTest extends TestCase
{
    private Parser $parser;
    private NumericEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->evaluator = new NumericEvaluator();
    }

    private function eval(string $expr, SymbolTable $symbols = new SymbolTable()): float
    {
        return $this->evaluator->evaluate($this->parser->parse($expr), $symbols);
    }

    public function testTrigFunctions(): void
    {
        self::assertEqualsWithDelta(1.0, $this->eval('sin(pi/2)'), 1e-9);
        self::assertEqualsWithDelta(1.0, $this->eval('cos(0)'), 1e-9);
    }

    public function testSqrtOfNegativeThrows(): void
    {
        $this->expectException(DomainException::class);
        $this->eval('sqrt(-1)');
    }

    public function testAsinOutOfDomainThrows(): void
    {
        $this->expectException(DomainException::class);
        $this->eval('asin(2)');
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        $this->eval('1/0');
    }

    public function testNegativeBaseFractionalExponentThrows(): void
    {
        $this->expectException(DomainException::class);
        $this->eval('(-8)^(1/3)');
    }

    public function testNthRootFunction(): void
    {
        self::assertEqualsWithDelta(2.0, $this->eval('root(8, 3)'), 1e-9);
    }

    public function testLnDomain(): void
    {
        $this->expectException(DomainException::class);
        $this->eval('ln(0)');
    }

    public function testVariableSubstitution(): void
    {
        $symbols = (new SymbolTable())->with('x', Rational::fromInt(4));
        self::assertEqualsWithDelta(2.0, $this->eval('sqrt(x)', $symbols), 1e-9);
    }

    public function testZeroToNegativePowerThrows(): void
    {
        $this->expectException(DivisionByZeroException::class);
        $this->eval('0^-1');
    }
}
