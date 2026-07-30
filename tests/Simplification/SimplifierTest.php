<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Simplification;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Simplification\Simplifier;

final class SimplifierTest extends TestCase
{
    private Parser $parser;
    private Simplifier $simplifier;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->simplifier = new Simplifier();
    }

    /** @dataProvider cases */
    public function testSimplifies(string $input, string $expected): void
    {
        $result = $this->simplifier->simplify($this->parser->parse($input));
        self::assertSame($expected, (string) $result);
    }

    public static function cases(): array
    {
        return [
            ['2 + 3 * 4', '14'],
            ['x + 0', 'x'],
            ['0 + x', 'x'],
            ['1 * x', 'x'],
            ['x * 1', 'x'],
            ['x * 0', '0'],
            ['0 * x', '0'],
            ['x / 1', 'x'],
            ['0 / x', '0'],
            ['x^1', 'x'],
            ['x^0', '1'],
            ['0^5', '0'],
            ['1^x', '1'],
            ['--x', 'x'],
            ['-(0)', '0'],
            ['2*3 + x*1 - 0', '(6 + x)'],
            ['x - 0', 'x'],
            ['0 - x', '(-x)'],
        ];
    }

    public function testDivisionByZeroThrowsDuringSimplification(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\DivisionByZeroException::class);
        $this->simplifier->simplify($this->parser->parse('x / 0'));
    }
}
