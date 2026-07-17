<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\{SymbolTable, NumericSolver};

final class NumericSolverTest extends TestCase
{
    private function solve(string $eq, string $unknown = 'x'): string
    {
        $solver = new NumericSolver(new SymbolTable());
        return (string) $solver->solve($eq, $unknown);
    }

    public function testSimpleLinearEquation(): void
    {
        $this->assertSame('4', $this->solve('2*x = 8'));
    }

    public function testFractionalSolution(): void
    {
        $this->assertSame('1/3', $this->solve('3*x = 1'));
    }

    public function testDistributedLinearEquation(): void
    {
        $this->assertSame('3', $this->solve('3*(x + 2) = 15'));
    }

    public function testNonlinearEquationIsRejected(): void
    {
        $this->expectExceptionMessageMatches('/Nonlinear/');
        $this->solve('sqrt(x+1) = 3');
    }
}
