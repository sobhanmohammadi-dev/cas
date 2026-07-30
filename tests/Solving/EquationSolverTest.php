<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Solving;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Solving\EquationSolver;

final class EquationSolverTest extends TestCase
{
    private Parser $parser;
    private EquationSolver $solver;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->solver = new EquationSolver();
    }

    private function solve(string $equation, string $variable = 'x'): array
    {
        /** @var EquationNode $node */
        $node = $this->parser->parse($equation);
        $solution = $this->solver->solve($node, $variable);
        $roots = array_map(fn ($r) => $r->toMathString(), $solution->roots);
        sort($roots);
        return $roots;
    }

    public function testLinearEquation(): void
    {
        self::assertSame(['-2'], $this->solve('2x + 4 = 0'));
    }

    public function testLinearEquationWithVariablesOnBothSides(): void
    {
        self::assertSame(['3'], $this->solve('3x + 1 = 2x + 4'));
    }

    public function testQuadraticEquationTwoRoots(): void
    {
        self::assertSame(['2', '3'], $this->solve('x^2 - 5x + 6 = 0'));
    }

    public function testQuadraticEquationRepeatedRoot(): void
    {
        self::assertSame(['3'], $this->solve('x^2 - 6x + 9 = 0'));
    }

    public function testQuadraticNoRealSolution(): void
    {
        /** @var EquationNode $node */
        $node = $this->parser->parse('x^2 + 1 = 0');
        $solution = $this->solver->solve($node, 'x');
        self::assertTrue($solution->hasNoRealSolution);
        self::assertSame([], $solution->roots);
    }

    public function testIdentity(): void
    {
        /** @var EquationNode $node */
        $node = $this->parser->parse('2 + 2 = 4');
        $solution = $this->solver->solve($node, 'x');
        self::assertTrue($solution->isIdentity);
    }

    public function testContradiction(): void
    {
        /** @var EquationNode $node */
        $node = $this->parser->parse('2 = 3');
        $solution = $this->solver->solve($node, 'x');
        self::assertTrue($solution->hasNoRealSolution);
    }

    public function testCubicThrows(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\UnsupportedOperationException::class);
        $this->solve('x^3 - 1 = 0');
    }
}
