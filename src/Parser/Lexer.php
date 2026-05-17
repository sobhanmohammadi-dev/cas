<?php
namespace CAS\Parser;

use CAS\Exception\MathParseException;
class Lexer
{
    private int $pos = 0;
    private int $len;
    private string $src;

    public function __construct(string $src)
    {
        $this->len = strlen($src);
        $this->src = $src;
    }

    public function tokenize(): array
    {
        $tokens = [];

        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];

            if (ctype_space($c)) {
                $this->pos++;
                continue;
            }

            if (ctype_digit($c) || $c === '.') {
                $tokens[] = $this->readNumber();
                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $tokens[] = $this->readWord();
                continue;
            }

            if ($c === '{') {
                $tokens[] = $this->readBraced();
                continue;
            }

            $start = $this->pos;
            $this->pos++;
            $end = $start;

            switch ($c) {
                case '+': $type = Token::PLUS;     $val = '+'; break;
                case '-': $type = Token::MINUS;    $val = '-'; break;
                case '*': $type = Token::MULTIPLY; $val = '*'; break;
                case '/': $type = Token::DIVIDE;   $val = '/'; break;
                case '^': $type = Token::POWER;    $val = '^'; break;
                case '(': $type = Token::LPAREN;   $val = '('; break;
                case ')': $type = Token::RPAREN;   $val = ')'; break;
                case '=': $type = Token::EQUALS;   $val = '='; break;
                case ',': $type = Token::COMMA;    $val = ','; break;
                default:
                    throw new MathParseException(
                        "Unexpected character '{$c}' at position {$start}."
                    );
            }

            $tokens[] = new Token($type, $val, $start, $end);
        }

        $tokens[] = new Token(Token::EOF, '', $this->pos, $this->pos);
        return $tokens;
    }

    private function readNumber(): Token
    {
        $start = $this->pos;
        $raw   = '';
        $dots  = 0;

        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];

            if (ctype_digit($c)) {
                $raw .= $c;
                $this->pos++;
            } elseif ($c === '.') {
                if ($dots > 0) {
                    throw new MathParseException(
                        "Malformed number literal: unexpected second '.' at position {$this->pos}."
                    );
                }
                $raw .= $c;
                $dots++;
                $this->pos++;
            } else {
                break;
            }
        }

        if ($raw === '' || $raw === '.') {
            throw new MathParseException("Malformed number literal at position {$start}.");
        }

        if ($this->pos < $this->len
            && ($this->src[$this->pos] === 'e' || $this->src[$this->pos] === 'E')
        ) {
            $eSave   = $this->pos;
            $rawSave = $raw;

            $raw .= $this->src[$this->pos++];

            if ($this->pos < $this->len
                && ($this->src[$this->pos] === '+' || $this->src[$this->pos] === '-')
            ) {
                $raw .= $this->src[$this->pos++];
            }

            if ($this->pos >= $this->len || !ctype_digit($this->src[$this->pos])) {
                $this->pos = $eSave;
                $raw       = $rawSave;
            } else {
                while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
                    $raw .= $this->src[$this->pos++];
                }
            }
        }

        $end = $this->pos - 1;
        return new Token(Token::NUMBER, $raw, $start, $end);
    }

    private function readWord(): Token
    {
        $start = $this->pos;
        $word  = '';

        while ($this->pos < $this->len
            && (ctype_alnum($this->src[$this->pos]) || $this->src[$this->pos] === '_')) {
            $word .= $this->src[$this->pos++];
        }

        $end = $this->pos - 1;

        switch (strtolower($word)) {
            case 'sqrt':
                return new Token(Token::SQRT, $word, $start, $end);
            case 'radical':
                return new Token(Token::RADICAL, $word, $start, $end);
            case 'pi':
                return new Token(Token::PI, $word, $start, $end);
            default:
                return new Token(Token::IDENTIFIER, $word, $start, $end);
        }
    }

    private function readBraced(): Token
    {
        $start = $this->pos;
        $this->pos++; // skip '{'
        $name = '';

        while ($this->pos < $this->len && $this->src[$this->pos] !== '}') {
            $c = $this->src[$this->pos];
            if (!ctype_alnum($c) && $c !== '_') {
                throw new MathParseException(
                    "Invalid character '{$c}' inside {} at position {$this->pos}."
                );
            }
            $name .= $c;
            $this->pos++;
        }

        if ($this->pos >= $this->len) {
            throw new MathParseException("Unclosed '{' starting at position {$start}.");
        }

        $this->pos++; // skip '}'
        $end = $this->pos - 1;

        if ($name === '') {
            throw new MathParseException("Empty variable name in {} at position {$start}.");
        }

        return new Token(Token::IDENTIFIER, $name, $start, $end);
    }
}