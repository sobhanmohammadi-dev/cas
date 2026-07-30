<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\MathParseException;
use Sobhanmohammadi\CAS\Lexing\Lexer;
use Sobhanmohammadi\CAS\Lexing\TokenType;

final class LexerTest extends TestCase
{
    public function testTokenizesSimpleExpression(): void
    {
        $tokens = (new Lexer())->tokenize('1 + 2');
        $types = array_map(fn ($t) => $t->type, $tokens);
        self::assertSame([TokenType::Number, TokenType::Plus, TokenType::Number, TokenType::EndOfInput], $types);
    }

    public function testTokenizesDecimalAndScientificNumbers(): void
    {
        $tokens = (new Lexer())->tokenize('3.14 2e10 .5 1e-3');
        $numbers = array_map(fn ($t) => $t->text, array_filter($tokens, fn ($t) => $t->type === TokenType::Number));
        self::assertSame(['3.14', '2e10', '.5', '1e-3'], array_values($numbers));
    }

    public function testTokenizesIdentifiers(): void
    {
        $tokens = (new Lexer())->tokenize('sin x_1');
        $ids = array_values(array_map(fn ($t) => $t->text, array_filter($tokens, fn ($t) => $t->type === TokenType::Identifier)));
        self::assertSame(['sin', 'x_1'], $ids);
    }

    public function testSkipsWhitespace(): void
    {
        $tokens = (new Lexer())->tokenize("  1  +\t2\n");
        self::assertCount(4, $tokens);
    }

    public function testThrowsOnUnknownCharacter(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer())->tokenize('1 + @');
    }

    public function testPositionsAreTracked(): void
    {
        $tokens = (new Lexer())->tokenize('12 + 3');
        self::assertSame(0, $tokens[0]->startPos);
        self::assertSame(2, $tokens[0]->endPos);
    }
}
