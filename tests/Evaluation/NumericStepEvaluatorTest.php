<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Evaluation;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Evaluation\NumericStepEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Exception\DomainException;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Parsing\Parser;

final class NumericStepEvaluatorTest extends TestCase
{
    private Parser $parser;
    private NumericStepEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->evaluator = new NumericStepEvaluator();
    }

    public function testSimpleArithmeticProducesOneStepPerOperation(): void
    {
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('2 + 3 * 4'));

        self::assertCount(2, $doc->steps);
        self::assertSame('Multiplication', $doc->steps[0]->rule->render());
        self::assertSame('Addition', $doc->steps[1]->rule->render());
        self::assertSame('14', $doc->finalResult->expression);
    }

    public function testComplexExpressionMatchesExpectedFinalValue(): void
    {
        $doc = $this->evaluator->evaluateWithSteps(
            $this->parser->parse('2 + sqrt(9 * 2 - (2^(0.8271 + 1) / 12)) - 19 / 1.263')
        );

        self::assertEqualsWithDelta(-8.8359, (float) $doc->finalResult->expression, 1e-3);
        self::assertGreaterThanOrEqual(8, count($doc->steps));

        $rules = array_map(fn ($s) => $s->rule->render(), $doc->steps);
        self::assertContains('Square Root', $rules);
        self::assertContains('Exponentiation', $rules);
    }

    public function testExponentiationStepIncludesLnDetails(): void
    {
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('2^1.8271'));
        $step = $doc->steps[0];

        self::assertSame('Exponentiation', $step->rule->render());
        self::assertArrayHasKey('ln_a', $step->details);
        self::assertArrayHasKey('b_times_ln_a', $step->details);
    }

    public function testStepsChainCorrectlyBeforeToAfter(): void
    {
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('(1 + 2) * (3 + 4)'));

        self::assertSame('(1 + 2)', $doc->steps[0]->targetExpression);
        self::assertSame('3', $doc->steps[0]->result);
    }

    public function testTrigFunctionStep(): void
    {
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('sin(0)'));
        self::assertSame('Sine', $doc->steps[0]->rule->render());
        self::assertSame('0', $doc->finalResult->expression);
    }

    public function testVariableSubstitution(): void
    {
        $symbols = (new SymbolTable())->with('x', Rational::fromInt(4));
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('sqrt(x) + 1'), $symbols);
        self::assertSame('3', $doc->finalResult->expression);
    }

    public function testDomainErrorStillThrows(): void
    {
        $this->expectException(DomainException::class);
        $this->evaluator->evaluateWithSteps($this->parser->parse('sqrt(-1)'));
    }

    public function testDocumentIsLocalizable(): void
    {
        $doc = $this->evaluator->evaluateWithSteps($this->parser->parse('1 + 1'));
        $array = $doc->toArray();

        self::assertSame('Step-by-Step Evaluation of a Mathematical Expression', $array['title']);
        self::assertSame('Addition', $array['steps'][0]['rule']);
    }
}
