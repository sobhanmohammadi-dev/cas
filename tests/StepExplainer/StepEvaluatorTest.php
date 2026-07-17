<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\StepEvaluator;

final class StepEvaluatorTest extends TestCase
{
    public function testEvaluateExpressionReturnsSteps(): void
    {
        $ev = new StepEvaluator(new SymbolTable());
        $steps = $ev->evaluateExpression('2 + 3 * 4');
        $this->assertNotEmpty($steps);
        foreach ($steps as $s) {
            $this->assertNotEmpty($s->getEn());
        }
    }

    public function testConstructorRequiresBcmath(): void
    {
        // bcmath is required and installed in this environment; just confirm
        // construction succeeds without throwing.
        $ev = new StepEvaluator(new SymbolTable());
        $this->assertInstanceOf(StepEvaluator::class, $ev);
    }

    public function testSquareRootOfNegativeThrows(): void
    {
        $ev = new StepEvaluator(new SymbolTable());
        $this->expectException(\RuntimeException::class);
        $ev->evaluateExpression('sqrt(-4)');
    }

    /**
     * Regression test: radical(0, 8) previously threw a raw, unhandled
     * DivisionByZeroError from bcdiv('1', '0', ...) instead of the library's
     * standard RuntimeException.
     */
    public function testRootWithZeroDegreeThrowsRuntimeExceptionNotDivisionByZeroError(): void
    {
        $ev = new StepEvaluator(new SymbolTable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Root degree cannot be zero.');
        $ev->evaluateExpression('radical(0, 8)');
    }

    public function testValidRootStillWorks(): void
    {
        $ev = new StepEvaluator(new SymbolTable());
        $steps = $ev->evaluateExpression('radical(3, 27)');
        $this->assertNotEmpty($steps);
    }
}
