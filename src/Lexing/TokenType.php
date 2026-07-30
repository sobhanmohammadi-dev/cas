<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Lexing;

enum TokenType
{
    case Number;
    case Identifier;
    case Plus;
    case Minus;
    case Star;
    case Slash;
    case Caret;
    case LParen;
    case RParen;
    case Comma;
    case Equals;
    case EndOfInput;
}
