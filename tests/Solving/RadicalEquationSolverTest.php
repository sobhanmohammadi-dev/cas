<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Solving;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\UnsupportedOperationException;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Solving\EquationSolver;

final class RadicalEquationSolverTest extends TestCase
{
    private Parser $parser;
    private EquationSolver $solver;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->solver = new EquationSolver();
    }

    private function equation(string $expr): EquationNode
    {
        /** @var EquationNode $node */
        $node = $this->parser->parse($expr);
        return $node;
    }

    public function testMatchesReferenceExample(): void
    {
        $solution = $this->solver->solve($this->equation('2 + sqrt(9x - 5) = 11'), 'x');
        self::assertSame(['86/9'], array_map(fn ($r) => (string) $r, $solution->roots));
    }

    public function testSimpleRadicalEquation(): void
    {
        $solution = $this->solver->solve($this->equation('sqrt(x) = 3'), 'x');
        self::assertSame(['9'], array_map(fn ($r) => (string) $r, $solution->roots));
    }

    public function testRadicalEqualsNegativeConstantHasNoRealSolution(): void
    {
        $solution = $this->solver->solve($this->equation('sqrt(x) = -3'), 'x');
        self::assertTrue($solution->hasNoRealSolution);
        self::assertSame([], $solution->roots);
    }

    public function testRadicalWithCoefficient(): void
    {
        $solution = $this->solver->solve($this->equation('2*sqrt(x) = 6'), 'x');
        self::assertSame(['9'], array_map(fn ($r) => (string) $r, $solution->roots));
    }

    public function testStepsIncludeIsolateSquareAndVerify(): void
    {
        $solved = $this->solver->solveWithSteps($this->equation('2 + sqrt(9x - 5) = 11'), 'x');
        $rules = array_map(fn ($s) => $s->rule->render(), $solved->steps);

        self::assertContains('Isolate the Square Root', $rules);
        self::assertContains('Square Both Sides', $rules);
        self::assertContains('Verify by Substitution', $rules);
    }

    public function testPolynomialEquationsStillWorkAfterRadicalFallbackAdded(): void
    {
        $solution = $this->solver->solve($this->equation('x^2 - 5x + 6 = 0'), 'x');
        self::assertSame(['3', '2'], array_map(fn ($r) => (string) $r, $solution->roots));
    }

    public function testVariableOutsideRadicalIsUnsupported(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->solver->solve($this->equation('sqrt(2x + 3) = 1 - x'), 'x');
    }
}
