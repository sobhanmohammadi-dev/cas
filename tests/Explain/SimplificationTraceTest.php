<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Explain;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Simplification\Simplifier;

final class SimplificationTraceTest extends TestCase
{
    public function testTraceReachesSameResultAsSimplify(): void
    {
        $parser = new Parser();
        $simplifier = new Simplifier();
        $node = $parser->parse('2*3 + x*1 - 0');

        $direct = $simplifier->simplify($node);
        $trace = $simplifier->simplifyWithSteps($node);

        self::assertTrue($direct->equals($trace->result));
    }

    public function testTraceRecordsAtLeastOneStepWhenSimplifiable(): void
    {
        $parser = new Parser();
        $simplifier = new Simplifier();
        $trace = $simplifier->simplifyWithSteps($parser->parse('x + 0'));

        self::assertNotEmpty($trace->steps);
        self::assertSame('x', (string) $trace->result);
    }

    public function testTraceRecordsNoStepsWhenAlreadySimplified(): void
    {
        $parser = new Parser();
        $simplifier = new Simplifier();
        $trace = $simplifier->simplifyWithSteps($parser->parse('x'));

        self::assertSame([], $trace->steps);
    }

    public function testEachStepsBeforeMatchesPreviousAfter(): void
    {
        $parser = new Parser();
        $simplifier = new Simplifier();
        $trace = $simplifier->simplifyWithSteps($parser->parse('2*3 + x*1 - 0'));

        $previous = (string) $parser->parse('2*3 + x*1 - 0');
        foreach ($trace->steps as $step) {
            self::assertSame($previous, $step->currentExpression);
            $previous = $step->updatedExpression;
        }
    }
}
