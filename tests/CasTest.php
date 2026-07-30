<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Cas;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Exception\InvalidExpressionException;
use Sobhanmohammadi\CAS\Number\Rational;

final class CasTest extends TestCase
{
    private Cas $cas;

    protected function setUp(): void
    {
        $this->cas = new Cas();
    }

    public function testSimplify(): void
    {
        self::assertSame('((6 * x) - 3)', (string) $this->cas->simplify('2(x + 3) + 4(x - 1) - 5'));
    }

    public function testSimplifyWithSteps(): void
    {
        $trace = $this->cas->simplifyWithSteps('2(x + 3) + 4(x - 1) - 5');
        self::assertNotEmpty($trace->steps);
        self::assertSame('((6 * x) - 3)', (string) $trace->result);
    }

    public function testEvaluateExact(): void
    {
        self::assertSame('14', (string) $this->cas->evaluateExact('2 + 3*4'));
    }

    public function testEvaluateNumeric(): void
    {
        self::assertEqualsWithDelta(1.0, $this->cas->evaluateNumeric('sin(pi/2)'), 1e-9);
    }

    public function testEvaluateNumericWithSteps(): void
    {
        $doc = $this->cas->evaluateNumericWithSteps('2 + sqrt(9 * 2 - (2^(0.8271 + 1) / 12)) - 19 / 1.263');
        self::assertEqualsWithDelta(-8.8359, (float) $doc->finalResult->expression, 1e-3);
    }

    public function testSolveFor(): void
    {
        $solution = $this->cas->solveFor('x^2 - 5x + 6 = 0', 'x');
        self::assertSame(['3', '2'], array_map(fn ($r) => (string) $r, $solution->roots));
    }

    public function testSolveForWithSteps(): void
    {
        $solved = $this->cas->solveForWithSteps('2 + sqrt(9x - 5) = 11', 'x');
        self::assertSame(['86/9'], array_map(fn ($r) => (string) $r, $solved->solution->roots));
    }

    public function testSolveForRejectsNonEquation(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->cas->solveFor('2 + 3', 'x');
    }

    public function testLocalizationRoundTrip(): void
    {
        $doc = $this->cas->evaluateNumericWithSteps('1 + 1');
        $catalog = $this->cas->extractLocalizationCatalog($doc);
        self::assertArrayHasKey('rule.addition', $catalog);

        $translated = $this->cas->translate($doc, ['rule.addition' => 'جمع']);
        self::assertSame('جمع', $translated->steps[0]->rule->render());
        self::assertSame($doc->steps[0]->result, $translated->steps[0]->result);
    }

    public function testAcceptsPreParsedNode(): void
    {
        $node = $this->cas->parse('2 + 2');
        self::assertSame('4', (string) $this->cas->simplify($node));
    }

    public function testEvaluateExactWithSymbols(): void
    {
        $symbols = (new SymbolTable())->with('x', Rational::fromInt(5));
        self::assertSame('11', (string) $this->cas->evaluateExact('2*x + 1', $symbols));
    }
}
