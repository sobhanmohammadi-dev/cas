<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Solving;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Solving\EquationSolver;

final class SolveWithStepsTest extends TestCase
{
    public function testLinearStepsProduceSameRootsAsSolve(): void
    {
        $parser = new Parser();
        $solver = new EquationSolver();
        /** @var EquationNode $eq */
        $eq = $parser->parse('3x + 6 = 0');

        $plain = $solver->solve($eq, 'x');
        $withSteps = $solver->solveWithSteps($eq, 'x');

        self::assertCount(count($plain->roots), $withSteps->solution->roots);
        self::assertNotEmpty($withSteps->steps);
    }

    public function testQuadraticStepsIncludeDiscriminant(): void
    {
        $parser = new Parser();
        $solver = new EquationSolver();
        /** @var EquationNode $eq */
        $eq = $parser->parse('x^2 - 5x + 6 = 0');

        $withSteps = $solver->solveWithSteps($eq, 'x');
        $descriptions = array_map(fn ($s) => $s->rule->render(), $withSteps->steps);

        self::assertContains('Compute the discriminant b^2 - 4ac', $descriptions);
        self::assertSame(['3', '2'], array_map(fn ($r) => (string) $r, $withSteps->solution->roots));
    }
}
