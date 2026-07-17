<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\StepSolver;

final class StepSolverTest extends TestCase
{
    public function testSolveReturnsNonEmptySteps(): void
    {
        $solver = new StepSolver(new SymbolTable());
        $steps = $solver->solve('2*x + 3 = 7', 'x');
        $this->assertNotEmpty($steps);
        $last = end($steps);
        $this->assertStringContainsString('x = 2', $last->getEn());
    }

    public function testDistributedEquationSolvesCorrectly(): void
    {
        $solver = new StepSolver(new SymbolTable());
        $steps = $solver->solve('3*(x + 2) = 15', 'x');
        $last = end($steps);
        $this->assertStringContainsString('x = 3', $last->getEn());
    }

    /**
     * Regression test: this equation is nonlinear (x appears in the
     * denominator, nested inside a Plus subtree) and must be rejected, not
     * silently mis-solved by the LinearSolverTrait namespace bug.
     */
    public function testNonlinearEquationIsRejected(): void
    {
        $solver = new StepSolver(new SymbolTable());
        $this->expectException(\RuntimeException::class);
        $solver->solve('x/(x+1) = 2', 'x');
    }

    public function testEachStepHasNonEmptyText(): void
    {
        $solver = new StepSolver(new SymbolTable());
        $steps = $solver->solve('2*x = 10', 'x');
        foreach ($steps as $s) {
            $this->assertNotEmpty($s->getEn());
        }
    }
}
