<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\MathFormatter;

final class MathFormatterTest extends TestCase
{
    public function testFormatReturnsExpectedStructure(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $result = $fmt->format('2 + 3 * 4');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('expression', $result);
        $this->assertArrayHasKey('steps', $result);
        $this->assertArrayHasKey('result', $result);
        $this->assertSame(14.0, (float) $result['result']['value']);
    }

    public function testEachStepHasRequiredFields(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $result = $fmt->format('(2^3 * sqrt(36)) / 4 + 5^2');

        $this->assertNotEmpty($result['steps']);
        foreach ($result['steps'] as $step) {
            foreach (['step', 'operation', 'target', 'before', 'after', 'formula', 'calculation', 'explanation'] as $field) {
                $this->assertArrayHasKey($field, $step);
            }
        }
    }

    public function testPersianLanguageOutput(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'fa');
        $result = $fmt->format('2 + 2');
        $this->assertSame(4.0, (float) $result['result']['value']);
    }

    public function testDivisionByZeroThrows(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $this->expectException(\RuntimeException::class);
        $fmt->format('5 / 0');
    }

    public function testSqrtOfNegativeThrows(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $this->expectException(\RuntimeException::class);
        $fmt->format('sqrt(-9)');
    }

    /**
     * Regression test: radical(0, 8) previously threw a raw, unhandled
     * DivisionByZeroError (from bcdiv('1', '0', ...) inside walkRoot())
     * instead of a clear, catchable RuntimeException.
     */
    public function testRootWithZeroDegreeThrowsRuntimeException(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Root degree cannot be zero.');
        $fmt->format('radical(0, 8)');
    }

    public function testValidCubeRootStillWorks(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $result = $fmt->format('radical(3, 27)');
        $this->assertSame(3.0, (float) $result['result']['value']);
    }

    public function testVariableSubstitution(): void
    {
        $sym = new SymbolTable();
        $sym->assign('x', new \Sobhanmohammadi\CAS\Nodes\IntegerNode('5', 0, 1));
        $fmt = new MathFormatter($sym, 'en');
        $result = $fmt->format('x + 3');
        $this->assertSame(8.0, (float) $result['result']['value']);
    }

    public function testUndefinedVariableThrows(): void
    {
        $fmt = new MathFormatter(new SymbolTable(), 'en');
        $this->expectException(\RuntimeException::class);
        $fmt->format('x + 1');
    }
}
