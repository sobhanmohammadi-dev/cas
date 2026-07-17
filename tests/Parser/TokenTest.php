<?php
namespace Sobhanmohammadi\CAS\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Parser\Token;

final class TokenTest extends TestCase
{
    public function testAccessors(): void
    {
        $t = new Token(Token::NUMBER, '42', 3, 4);
        $this->assertSame(Token::NUMBER, $t->getType());
        $this->assertSame('42', $t->getValue());
        $this->assertSame(3, $t->getStart());
        $this->assertSame(4, $t->getEnd());
    }

    public function testConstantsAreDistinct(): void
    {
        $constants = [
            Token::NUMBER, Token::IDENTIFIER, Token::PLUS, Token::MINUS,
            Token::MULTIPLY, Token::DIVIDE, Token::POWER, Token::LPAREN,
            Token::RPAREN, Token::EQUALS, Token::COMMA, Token::SQRT,
            Token::RADICAL, Token::PI, Token::EOF,
        ];
        $this->assertSame(count($constants), count(array_unique($constants)));
    }
}
