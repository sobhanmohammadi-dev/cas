<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\SymbolicStepEvaluator;
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};

final class SymbolicStepEvaluatorTest extends TestCase
{
    private function parse(string $src)
    {
        $tokens = (new Lexer($src))->tokenize();
        return (new Parser($tokens, $src))->parse();
    }

    public function testEvaluateSimplifiesAndRecordsSteps(): void
    {
        $ev = new SymbolicStepEvaluator(new SymbolTable());
        $result = $ev->evaluate($this->parse('2*x + 3*x'));
        $this->assertSame('(5 * x)', (string) $result);
        $this->assertNotEmpty($ev->getSteps());
    }

    public function testResetBetweenEvaluations(): void
    {
        $ev = new SymbolicStepEvaluator(new SymbolTable());
        $ev->evaluate($this->parse('2*x + 3*x'));
        $firstCount = count($ev->getSteps());
        $this->assertGreaterThan(0, $firstCount);

        // A trivial expression that undergoes no rule applications should
        // reset the recorder rather than accumulate steps from before.
        $ev->evaluate($this->parse('7'));
        $this->assertCount(0, $ev->getSteps());
    }

    public function testNoStepsForAlreadySimplifiedConstant(): void
    {
        $ev = new SymbolicStepEvaluator(new SymbolTable());
        $result = $ev->evaluate($this->parse('42'));
        $this->assertSame('42', (string) $result);
    }
}
