<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\{SymbolTable, SymbolicSolver};

final class SymbolicSolverTest extends TestCase
{
    private function solve(string $eq, string $unknown = 'x'): string
    {
        $solver = new SymbolicSolver(new SymbolTable());
        return (string) $solver->solve($eq, $unknown);
    }

    public function testSimpleLinearEquation(): void
    {
        $this->assertSame('4', $this->solve('2*x = 8'));
    }

    public function testLinearEquationWithConstantOnBothSides(): void
    {
        $this->assertSame('3', $this->solve('2*x + 1 = 7'));
    }

    public function testDistributedLinearEquation(): void
    {
        $this->assertSame('3', $this->solve('3*(x + 2) = 15'));
    }

    public function testContradictionThrows(): void
    {
        $this->expectExceptionMessage('No solution (contradiction).');
        $this->solve('x + 1 = x + 2');
    }

    public function testIdentityThrows(): void
    {
        $this->expectExceptionMessage('Infinite solutions (identity).');
        $this->solve('x + 1 = x + 1');
    }

    public function testNonlinearEquationIsRejected(): void
    {
        $this->expectExceptionMessageMatches('/Nonlinear/');
        $this->solve('x/(x+1) = 2');
    }

    public function testSymbolTablePreexistingValueIsRestoredAfterSolve(): void
    {
        $sym = new SymbolTable();
        $sym->assign('x', new \Sobhanmohammadi\CAS\Nodes\IntegerNode('99', 0, 1));
        $solver = new SymbolicSolver($sym);
        $solver->solve('2*x = 8', 'x');
        // The temporarily-removed binding for 'x' must be restored afterwards.
        $this->assertSame('99', (string) $sym->lookup('x'));
    }
}
