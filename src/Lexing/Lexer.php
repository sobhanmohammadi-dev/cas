<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Lexing;

use Sobhanmohammadi\CAS\Exception\MathParseException;

/**
 * Turns a source string into a flat list of tokens. Whitespace is skipped;
 * numbers (including decimals and scientific notation) and identifiers
 * (variables/function names) are each captured as a single token.
 */
final class Lexer
{
    private const SIMPLE = [
        '+' => TokenType::Plus,
        '-' => TokenType::Minus,
        '*' => TokenType::Star,
        '/' => TokenType::Slash,
        '^' => TokenType::Caret,
        '(' => TokenType::LParen,
        ')' => TokenType::RParen,
        ',' => TokenType::Comma,
        '=' => TokenType::Equals,
    ];

    /** @return Token[] */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $len = strlen($source);
        $i = 0;

        while ($i < $len) {
            $ch = $source[$i];

            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if (isset(self::SIMPLE[$ch])) {
                $tokens[] = new Token(self::SIMPLE[$ch], $ch, $i, $i + 1);
                $i++;
                continue;
            }

            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $len && ctype_digit($source[$i + 1]))) {
                $start = $i;
                $i = $this->consumeNumber($source, $i, $len);
                $tokens[] = new Token(TokenType::Number, substr($source, $start, $i - $start), $start, $i);
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($source[$i]) || $source[$i] === '_')) {
                    $i++;
                }
                $tokens[] = new Token(TokenType::Identifier, substr($source, $start, $i - $start), $start, $i);
                continue;
            }

            throw new MathParseException("Unexpected character '{$ch}' at position {$i}.");
        }

        $tokens[] = new Token(TokenType::EndOfInput, '', $len, $len);
        return $tokens;
    }

    private function consumeNumber(string $source, int $i, int $len): int
    {
        while ($i < $len && ctype_digit($source[$i])) {
            $i++;
        }
        if ($i < $len && $source[$i] === '.') {
            $i++;
            while ($i < $len && ctype_digit($source[$i])) {
                $i++;
            }
        }
        if ($i < $len && ($source[$i] === 'e' || $source[$i] === 'E')) {
            $j = $i + 1;
            if ($j < $len && ($source[$j] === '+' || $source[$j] === '-')) {
                $j++;
            }
            if ($j < $len && ctype_digit($source[$j])) {
                $i = $j;
                while ($i < $len && ctype_digit($source[$i])) {
                    $i++;
                }
            }
        }
        return $i;
    }
}
