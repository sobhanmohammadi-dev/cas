<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\MathParseException;
use Sobhanmohammadi\CAS\Parsing\Parser;

final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /** @dataProvider expressions */
    public function testParsesToExpectedString(string $input, string $expected): void
    {
        self::assertSame($expected, (string) $this->parser->parse($input));
    }

    public static function expressions(): array
    {
        return [
            ['2 + 3 * 4', '(2 + (3 * 4))'],
            ['(2 + 3) * 4', '((2 + 3) * 4)'],
            ['2x + 3', '((2 * x) + 3)'],
            ['2(x+1)', '(2 * (x + 1))'],
            ['x(x+1)', '(x * (x + 1))'],
            ['-x^2', '(-(x ^ 2))'],
            ['2^3^2', '(2 ^ (3 ^ 2))'], // right-associative power
            ['3xy', '(3 * (x * y))'],
            ['sin(pi/2)', 'sin((pi / 2))'],
            ['atan2(1, 1)', 'atan2(1, 1)'],
            ['x^2 - 4 = 0', '((x ^ 2) - 4) = 0'],
        ];
    }

    public function testUnexpectedCharacterThrows(): void
    {
        $this->expectException(MathParseException::class);
        $this->parser->parse('2 + @');
    }

    public function testUnbalancedParenThrows(): void
    {
        $this->expectException(MathParseException::class);
        $this->parser->parse('(2 + 3');
    }

    public function testWrongFunctionArityThrows(): void
    {
        $this->expectException(\Sobhanmohammadi\CAS\Exception\InvalidExpressionException::class);
        $this->parser->parse('sin(1, 2)');
    }
}
