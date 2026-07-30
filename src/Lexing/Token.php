<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Lexing;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $text,
        public readonly int $startPos,
        public readonly int $endPos,
    ) {
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
