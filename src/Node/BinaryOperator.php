<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Node;

enum BinaryOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Power = '^';

    public function precedence(): int
    {
        return match ($this) {
            self::Add, self::Subtract => 1,
            self::Multiply, self::Divide => 2,
            self::Power => 3,
        };
    }

    public function isRightAssociative(): bool
    {
        return $this === self::Power;
    }
}
