<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Parsing;

use Sobhanmohammadi\CAS\Exception\MathParseException;
use Sobhanmohammadi\CAS\Lexing\Lexer;
use Sobhanmohammadi\CAS\Lexing\Token;
use Sobhanmohammadi\CAS\Lexing\TokenType;
use Sobhanmohammadi\CAS\Node\BinaryNode;
use Sobhanmohammadi\CAS\Node\BinaryOperator;
use Sobhanmohammadi\CAS\Node\ConstantKind;
use Sobhanmohammadi\CAS\Node\ConstantNode;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\FunctionKind;
use Sobhanmohammadi\CAS\Node\FunctionNode;
use Sobhanmohammadi\CAS\Node\NegateNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Node\NumberNode;
use Sobhanmohammadi\CAS\Node\VariableNode;

/**
 * A precedence-climbing recursive-descent parser.
 *
 * Grammar (informal):
 *   equation   := expr ( '=' expr )?
 *   expr       := term ( ('+' | '-') term )*
 *   term       := unary ( ('*' | '/') unary | implicitMul )*
 *   unary      := '-' unary | power
 *   power      := postfix ( '^' unary )?
 *   postfix    := primary
 *   primary    := NUMBER | IDENTIFIER | function '(' args ')' | '(' expr ')'
 *
 * Implicit multiplication (e.g. "2x", "2(x+1)", "x(x+1)") is handled in
 * term() by checking whether the next token can start a new unary
 * expression without an explicit '*' between them.
 */
final class Parser
{
    /** @var Token[] */
    private array $tokens;
    private int $pos = 0;

    public function parse(string $source): Node
    {
        $this->tokens = (new Lexer())->tokenize($source);
        $this->pos = 0;

        $node = $this->parseEquationOrExpression();
        $this->expect(TokenType::EndOfInput, 'end of input');
        return $node;
    }

    private function parseEquationOrExpression(): Node
    {
        $left = $this->parseExpression();
        if ($this->check(TokenType::Equals)) {
            $eq = $this->advance();
            $right = $this->parseExpression();
            return new EquationNode($left, $right, $left->startPos, $right->endPos);
        }
        return $left;
    }

    private function parseExpression(): Node
    {
        $node = $this->parseTerm();
        while ($this->check(TokenType::Plus) || $this->check(TokenType::Minus)) {
            $opToken = $this->advance();
            $operator = $opToken->type === TokenType::Plus ? BinaryOperator::Add : BinaryOperator::Subtract;
            $right = $this->parseTerm();
            $node = new BinaryNode($operator, $node, $right, $node->startPos, $right->endPos);
        }
        return $node;
    }

    private function parseTerm(): Node
    {
        $node = $this->parseUnary();
        while (true) {
            if ($this->check(TokenType::Star) || $this->check(TokenType::Slash)) {
                $opToken = $this->advance();
                $operator = $opToken->type === TokenType::Star ? BinaryOperator::Multiply : BinaryOperator::Divide;
                $right = $this->parseUnary();
                $node = new BinaryNode($operator, $node, $right, $node->startPos, $right->endPos);
                continue;
            }
            if ($this->canStartImplicitFactor()) {
                $right = $this->parseUnary();
                $node = new BinaryNode(BinaryOperator::Multiply, $node, $right, $node->startPos, $right->endPos);
                continue;
            }
            break;
        }
        return $node;
    }

    private function canStartImplicitFactor(): bool
    {
        return $this->check(TokenType::Identifier) || $this->check(TokenType::LParen);
    }

    private function parseUnary(): Node
    {
        if ($this->check(TokenType::Minus)) {
            $minus = $this->advance();
            $operand = $this->parseUnary();
            return new NegateNode($operand, $minus->startPos, $operand->endPos);
        }
        if ($this->check(TokenType::Plus)) {
            $this->advance();
            return $this->parseUnary();
        }
        return $this->parsePower();
    }

    private function parsePower(): Node
    {
        $base = $this->parsePrimary();
        if ($this->check(TokenType::Caret)) {
            $this->advance();
            $exponent = $this->parseUnary(); // right-associative, allows -x exponents
            return new BinaryNode(BinaryOperator::Power, $base, $exponent, $base->startPos, $exponent->endPos);
        }
        return $base;
    }

    private function parsePrimary(): Node
    {
        $token = $this->current();

        if ($token->type === TokenType::Number) {
            $this->advance();
            return NumberNode::fromDecimalString($token->text, $token->startPos, $token->endPos);
        }

        if ($token->type === TokenType::LParen) {
            $this->advance();
            $inner = $this->parseExpression();
            $this->expect(TokenType::RParen, "')'");
            return $inner;
        }

        if ($token->type === TokenType::Identifier) {
            return $this->parseIdentifier();
        }

        throw new MathParseException("Unexpected token '{$token->text}' at position {$token->startPos}.");
    }

    private function parseIdentifier(): Node
    {
        $token = $this->advance();
        $name = strtolower($token->text);

        $kind = FunctionKind::tryFrom($name);
        if ($kind !== null && $this->check(TokenType::LParen)) {
            $this->advance();
            $args = [$this->parseExpression()];
            while ($this->check(TokenType::Comma)) {
                $this->advance();
                $args[] = $this->parseExpression();
            }
            $close = $this->expect(TokenType::RParen, "')'");
            return new FunctionNode($kind, $args, $token->startPos, $close->endPos);
        }

        if ($name === 'pi') {
            return new ConstantNode(ConstantKind::Pi, $token->startPos, $token->endPos);
        }
        if ($name === 'e') {
            return new ConstantNode(ConstantKind::E, $token->startPos, $token->endPos);
        }

        // Support things like "2xy" by splitting a multi-letter identifier
        // (that is not a known function) into implicit multiplication of
        // single-letter variables: x*y.
        if (strlen($token->text) > 1) {
            $chars = str_split($token->text);
            $node = new VariableNode($chars[0], $token->startPos, $token->startPos + 1);
            $offset = $token->startPos + 1;
            foreach (array_slice($chars, 1) as $char) {
                $var = new VariableNode($char, $offset, $offset + 1);
                $node = new BinaryNode(BinaryOperator::Multiply, $node, $var, $node->startPos, $var->endPos);
                $offset++;
            }
            return $node;
        }

        return new VariableNode($token->text, $token->startPos, $token->endPos);
    }

    private function current(): Token
    {
        return $this->tokens[$this->pos];
    }

    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function advance(): Token
    {
        $token = $this->tokens[$this->pos];
        if ($token->type !== TokenType::EndOfInput) {
            $this->pos++;
        }
        return $token;
    }

    private function expect(TokenType $type, string $description): Token
    {
        if (!$this->check($type)) {
            $token = $this->current();
            throw new MathParseException("Expected {$description} but found '{$token->text}' at position {$token->startPos}.");
        }
        return $this->advance();
    }
}
