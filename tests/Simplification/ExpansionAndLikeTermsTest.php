<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Simplification;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Simplification\Simplifier;

final class ExpansionAndLikeTermsTest extends TestCase
{
    private Parser $parser;
    private Simplifier $simplifier;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->simplifier = new Simplifier();
    }

    private function simplify(string $expr): string
    {
        return (string) $this->simplifier->simplify($this->parser->parse($expr));
    }

    /** @dataProvider expressionProvider */
    public function testSimplifiesToExpected(string $input, string $expectedContains): void
    {
        self::assertSame($expectedContains, $this->simplify($input));
    }

    public static function expressionProvider(): array
    {
        return [
            ['2(x + 3) + 4(x - 1) - 5', '((6 * x) - 3)'],
            ['3x + 2 - x', '((2 * x) + 2)'],
            ['2x + 4x', '(6 * x)'],
            ['x + x + x', '(3 * x)'],
            ['5 - 2 - 3', '0'],
            ['x - x', '0'],
            ['2*(x - 1)', '((2 * x) - 2)'],
            ['(x + 1) * 2', '((x * 2) + 2)'],
        ];
    }

    public function testDoesNotCombineUnrelatedTerms(): void
    {
        self::assertSame('(x + y)', $this->simplify('x + y'));
    }

    public function testCombinesFunctionCallTerms(): void
    {
        self::assertSame('(5 * sin(x))', $this->simplify('2*sin(x) + 3*sin(x)'));
    }

    public function testExpansionAndCollectionAreIdempotent(): void
    {
        $once = $this->simplifier->simplify($this->parser->parse('2(x + 3) + 4(x - 1) - 5'));
        $twice = $this->simplifier->simplify($once);
        self::assertTrue($once->equals($twice));
    }

    public function testStepTraceEndsAtSameResultAsDirectSimplify(): void
    {
        $node = $this->parser->parse('2(x + 3) + 4(x - 1) - 5');
        $direct = $this->simplifier->simplify($node);
        $trace = $this->simplifier->simplifyWithSteps($node);

        self::assertTrue($direct->equals($trace->result));
        self::assertNotEmpty($trace->steps);
    }

    public function testTraceIncludesDistributiveAndLikeTermsSteps(): void
    {
        $trace = $this->simplifier->simplifyWithSteps($this->parser->parse('2(x + 3) + 4(x - 1) - 5'));
        $rules = array_map(fn ($s) => $s->rule->render(), $trace->steps);

        self::assertContains('Distributive Property', $rules);
        self::assertContains('Combine Like Terms', $rules);
    }
}
