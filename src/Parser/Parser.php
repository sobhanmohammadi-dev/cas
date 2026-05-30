<?php
namespace CAS\Parser;

use CAS\Exception\MathParseException;
use CAS\Nodes\{
    EquationNode,
    MathNode,
    NumericNode,
    PlusNode,
    MinusNode,
    MultiplyNode,
    DivideNode,
    PowerNode,
    UnaryNode,
    SqrtNode,
    RootNode,
    PiNode,
    VariableNode
};

class Parser
{
    private int    $pos   = 0;
    private int    $depth = 0;
    private array  $tokens;
    private string $src;

    public function __construct(array $tokens, string $src)
    {
        $this->tokens = $tokens;
        $this->src    = $src;
    }

    public function parse(): MathNode
    {
        $node = $this->expr();
        $cur  = $this->cur();
        if ($cur->getType() !== Token::EOF) {
            throw new MathParseException(
                "Unexpected token '{$cur->getValue()}' at position {$cur->getStart()}."
            );
        }
        return $node;
    }

    public function parseEquation(): EquationNode
    {
        $left = $this->expr();
        $this->expect(Token::EQUALS);
        $right = $this->expr();

        if ($this->cur()->getType() !== Token::EOF) {
            $t = $this->cur();
            throw new MathParseException(
                "Unexpected token '{$t->getValue()}' after equation at position {$t->getStart()}."
            );
        }

        return new EquationNode($left, $right, $left->getStartPos(), $right->getEndPos());
    }

    // ─── Grammar ──────────────────────────────────────────────────────────

    private function expr(): MathNode
    {
        $this->guard();
        try {
            $left = $this->term();
            while (true) {
                $type = $this->cur()->getType();
                if ($type !== Token::PLUS && $type !== Token::MINUS) break;
                $tok   = $this->eat();
                $right = $this->term();
                $left  = $this->binaryNode(
                    $left, $tok->getType(), $right,
                    $left->getStartPos(), $right->getEndPos()
                );
            }
            return $left;
        } finally {
            --$this->depth;
        }
    }

    private function term(): MathNode
    {
        $this->guard();
        try {
            $left = $this->factor();
            while (true) {
                $type = $this->cur()->getType();
                if ($type !== Token::MULTIPLY && $type !== Token::DIVIDE) break;
                $tok   = $this->eat();
                $right = $this->factor();
                $left  = $this->binaryNode(
                    $left, $tok->getType(), $right,
                    $left->getStartPos(), $right->getEndPos()
                );
            }
            return $left;
        } finally {
            --$this->depth;
        }
    }

    private function factor(): MathNode
    {
        $this->guard();
        try {
            $left = $this->unary();
            while ($this->isImplicitFactor()) {
                $right = $this->unary();
                $left  = new MultiplyNode($left, $right, $left->getStartPos(), $right->getEndPos());
            }
            return $left;
        } finally {
            --$this->depth;
        }
    }

    private function unary(): MathNode
    {
        if ($this->cur()->getType() === Token::MINUS) {
            $start = $this->cur()->getStart();
            $this->eat();
            $inner = $this->unary();
            return new UnaryNode('-', $inner, $start, $inner->getEndPos());
        }
        if ($this->cur()->getType() === Token::PLUS) {
            $this->eat();
            return $this->unary();
        }
        return $this->power();
    }

    private function power(): MathNode
    {
        $this->guard();
        try {
            $base = $this->primary();
            if ($this->cur()->getType() === Token::POWER) {
                $this->eat();
                $exp = $this->unary();   // right-associative
                return new PowerNode($base, $exp, $base->getStartPos(), $exp->getEndPos());
            }
            return $base;
        } finally {
            --$this->depth;
        }
    }

    private function primary(): MathNode
    {
        $this->guard();
        try {
            $t    = $this->cur();
            $type = $t->getType();

            if ($type === Token::NUMBER) {
                $this->eat();
                return NumericNode::fromDecimalString($t->getValue(), $t->getStart(), $t->getEnd());
            }

            if ($type === Token::PI) {
                $this->eat();
                return new PiNode($t->getStart(), $t->getEnd());
            }

            if ($type === Token::IDENTIFIER) {
                $this->eat();
                return new VariableNode($t->getValue(), $t->getStart(), $t->getEnd());
            }

            if ($type === Token::SQRT) {
                $s = $t->getStart();
                $this->eat();
                $this->expect(Token::LPAREN);
                $arg = $this->expr();
                $rp  = $this->expect(Token::RPAREN);
                return new SqrtNode($arg, $s, $rp->getEnd());
            }

            if ($type === Token::RADICAL) {
                $s = $t->getStart();
                $this->eat();
                $this->expect(Token::LPAREN);
                $deg = $this->expr();
                $this->expect(Token::COMMA);
                $rad = $this->expr();
                $rp  = $this->expect(Token::RPAREN);
                // RootNode(degree, radicand, ...)
                return new RootNode($deg, $rad, $s, $rp->getEnd());
            }

            if ($type === Token::LPAREN) {
                $s = $t->getStart();
                $this->eat();
                if ($this->cur()->getType() === Token::RPAREN) {
                    throw new MathParseException("Empty parentheses at position {$s}.");
                }
                $inner = $this->expr();
                $this->expect(Token::RPAREN);
                return $inner;
            }

            $label = $t->getValue() !== '' ? $t->getValue() : $t->getType();
            throw new MathParseException("Unexpected token '{$label}' at position {$t->getStart()}.");
        } finally {
            --$this->depth;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function cur(): Token
    {
        return $this->tokens[$this->pos]
            ?? new Token(Token::EOF, '', strlen($this->src), strlen($this->src));
    }

    private function eat(): Token
    {
        return $this->tokens[$this->pos++]
            ?? new Token(Token::EOF, '', strlen($this->src), strlen($this->src));
    }

    private function expect(string $type): Token
    {
        $cur = $this->cur();
        if ($cur->getType() !== $type) {
            $got = $cur->getValue() !== '' ? $cur->getValue() : $cur->getType();
            throw new MathParseException(
                "Expected '{$type}', got '{$got}' at position {$cur->getStart()}."
            );
        }
        return $this->eat();
    }

    private function guard(): void
    {
        if (++$this->depth > 200) {
            throw new MathParseException('Expression too deeply nested (max depth: 200).');
        }
    }

    private function isImplicitFactor(): bool
    {
        return in_array($this->cur()->getType(), [
            Token::NUMBER,
            Token::IDENTIFIER,
            Token::PI,
            Token::LPAREN,
            Token::SQRT,
            Token::RADICAL,
        ], true);
    }

    private function binaryNode(
        MathNode $left,
        string   $opType,
        MathNode $right,
        int      $start,
        int      $end
    ): MathNode {
        switch ($opType) {
            case Token::PLUS:     return new PlusNode($left, $right, $start, $end);
            case Token::MINUS:    return new MinusNode($left, $right, $start, $end);
            case Token::MULTIPLY: return new MultiplyNode($left, $right, $start, $end);
            case Token::DIVIDE:   return new DivideNode($left, $right, $start, $end);
            case Token::POWER:    return new PowerNode($left, $right, $start, $end);
            default:
                throw new \InvalidArgumentException("Unknown binary operator: {$opType}");
        }
    }
}
