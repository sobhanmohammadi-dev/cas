<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

/**
 * Every named function the parser/evaluator understands. Consolidating
 * these into one enum (instead of one class per function, as in the
 * previous design) removes a large amount of boilerplate and duplicated
 * dispatch logic while keeping each case's arity explicit.
 */
enum FunctionKind: string
{
    case Sin = 'sin';
    case Cos = 'cos';
    case Tan = 'tan';
    case Asin = 'asin';
    case Acos = 'acos';
    case Atan = 'atan';
    case Atan2 = 'atan2';
    case Sqrt = 'sqrt';
    case Root = 'root';
    case Abs = 'abs';
    case Ln = 'ln';
    case Log = 'log';
    case Exp = 'exp';

    public function arity(): int
    {
        return match ($this) {
            self::Atan2, self::Root, self::Log => 2,
            default => 1,
        };
    }

    public function isTrigonometric(): bool
    {
        return match ($this) {
            self::Sin, self::Cos, self::Tan, self::Asin, self::Acos, self::Atan, self::Atan2 => true,
            default => false,
        };
    }
}
