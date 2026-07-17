<?php
namespace Sobhanmohammadi\CAS\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parser\Lexer;
use Sobhanmohammadi\CAS\Parser\Token;
use Sobhanmohammadi\CAS\Exception\MathParseException;

final class LexerTest extends TestCase
{
    private function types(string $src): array
    {
        return array_map(fn(Token $t) => $t->getType(), (new Lexer($src))->tokenize());
    }

    public function testSimpleArithmeticTokens(): void
    {
        $this->assertSame(
            [Token::NUMBER, Token::PLUS, Token::NUMBER, Token::EOF],
            $this->types('2 + 3')
        );
    }

    public function testAllOperatorsAndParens(): void
    {
        $this->assertSame(
            [Token::LPAREN, Token::NUMBER, Token::MINUS, Token::NUMBER, Token::RPAREN,
             Token::MULTIPLY, Token::NUMBER, Token::DIVIDE, Token::NUMBER,
             Token::POWER, Token::NUMBER, Token::EOF],
            $this->types('(1 - 2) * 3 / 4 ^ 5')
        );
    }

    public function testKeywordsAreRecognised(): void
    {
        $this->assertSame(
            [Token::SQRT, Token::LPAREN, Token::NUMBER, Token::RPAREN, Token::EOF],
            $this->types('sqrt(4)')
        );
        $this->assertSame(
            [Token::RADICAL, Token::LPAREN, Token::NUMBER, Token::COMMA, Token::NUMBER, Token::RPAREN, Token::EOF],
            $this->types('radical(3, 8)')
        );
        $this->assertSame([Token::PI, Token::EOF], $this->types('pi'));
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $this->assertSame([Token::PI, Token::EOF], $this->types('PI'));
        $this->assertSame([Token::SQRT, Token::LPAREN, Token::NUMBER, Token::RPAREN, Token::EOF], $this->types('SQRT(4)'));
    }

    public function testIdentifierTokenValue(): void
    {
        $tokens = (new Lexer('foo_bar'))->tokenize();
        $this->assertSame(Token::IDENTIFIER, $tokens[0]->getType());
        $this->assertSame('foo_bar', $tokens[0]->getValue());
    }

    public function testBracedIdentifier(): void
    {
        $tokens = (new Lexer('{x1}'))->tokenize();
        $this->assertSame(Token::IDENTIFIER, $tokens[0]->getType());
        $this->assertSame('x1', $tokens[0]->getValue());
    }

    public function testUnclosedBraceThrows(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer('{x'))->tokenize();
    }

    public function testEmptyBraceThrows(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer('{}'))->tokenize();
    }

    public function testInvalidCharacterInBraceThrows(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer('{x-1}'))->tokenize();
    }

    public function testMalformedNumberDoubleDecimalPointThrows(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer('1.2.3'))->tokenize();
    }

    public function testUnexpectedCharacterThrows(): void
    {
        $this->expectException(MathParseException::class);
        (new Lexer('2 @ 3'))->tokenize();
    }

    public function testWhitespaceIsSkipped(): void
    {
        $this->assertSame(
            [Token::NUMBER, Token::PLUS, Token::NUMBER, Token::EOF],
            $this->types("  2\t+\n3  ")
        );
    }

    public function testScientificNotationPositiveExponent(): void
    {
        $tokens = (new Lexer('1.5e2'))->tokenize();
        $this->assertSame(Token::NUMBER, $tokens[0]->getType());
        $this->assertSame('1.5e2', $tokens[0]->getValue());
    }

    public function testScientificNotationWithExplicitSign(): void
    {
        $tokens = (new Lexer('2E-3'))->tokenize();
        $this->assertSame('2E-3', $tokens[0]->getValue());
    }

    public function testTrailingEWithoutDigitsBacktracks(): void
    {
        // "5e" with nothing usable after 'e' should not be swallowed into the number;
        // 'e' has no digit following so it backtracks and 'e' becomes an identifier.
        $tokens = (new Lexer('5e'))->tokenize();
        $this->assertSame(Token::NUMBER, $tokens[0]->getType());
        $this->assertSame('5', $tokens[0]->getValue());
        $this->assertSame(Token::IDENTIFIER, $tokens[1]->getType());
        $this->assertSame('e', $tokens[1]->getValue());
    }

    public function testEofTokenAlwaysAppended(): void
    {
        $tokens = (new Lexer(''))->tokenize();
        $this->assertCount(1, $tokens);
        $this->assertSame(Token::EOF, $tokens[0]->getType());
    }
}
