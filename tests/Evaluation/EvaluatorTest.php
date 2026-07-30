<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Evaluation;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Evaluation\NumericEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Parsing\Parser;

final class EvaluatorTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testExactEvaluationOfArithmetic(): void
    {
        $result = (new ExactEvaluator())->evaluate($this->parser->parse('2 + 3 * (4 - 1) / 2'));
        self::assertSame('13/2', $result->toMathString());
    }

    public function testExactEvaluationWithVariables(): void
    {
        $symbols = (new SymbolTable())->with('x', Rational::fromInt(5));
        $result = (new ExactEvaluator())->evaluate($this->parser->parse('x^2 + 1'), $symbols);
        self::assertSame('26', $result->toMathString());
    }

    public function testExactEvaluationRejectsTranscendental(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\UnsupportedOperationException::class);
        (new ExactEvaluator())->evaluate($this->parser->parse('sin(1)'));
    }

    public function testUnboundVariableThrows(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\UnboundVariableException::class);
        (new ExactEvaluator())->evaluate($this->parser->parse('x + 1'));
    }

    public function testNumericEvaluationOfTrig(): void
    {
        $result = (new NumericEvaluator())->evaluate($this->parser->parse('sin(pi/2)'));
        self::assertEqualsWithDelta(1.0, $result, 1e-9);
    }

    public function testNumericSquareRootOfNegativeThrows(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\DomainException::class);
        (new NumericEvaluator())->evaluate($this->parser->parse('sqrt(-1)'));
    }

    public function testNumericDivisionByZeroThrows(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\DivisionByZeroException::class);
        (new NumericEvaluator())->evaluate($this->parser->parse('1/0'));
    }
}
