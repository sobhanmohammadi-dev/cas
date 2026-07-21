<?php
namespace Sobhanmohammadi\CAS\Parser;

class Token
{
    public const NUMBER     = 'NUMBER';
    public const IDENTIFIER = 'IDENTIFIER';
    public const PLUS       = 'PLUS';
    public const MINUS      = 'MINUS';
    public const MULTIPLY   = 'MULTIPLY';
    public const DIVIDE     = 'DIVIDE';
    public const POWER      = 'POWER';
    public const LPAREN     = 'LPAREN';
    public const RPAREN     = 'RPAREN';
    public const EQUALS     = 'EQUALS';
    public const COMMA      = 'COMMA';
    public const SQRT       = 'SQRT';
    public const RADICAL    = 'RADICAL';
    public const PI         = 'PI';
    public const SIN        = 'SIN';
    public const COS        = 'COS';
    public const TAN        = 'TAN';
    public const ASIN       = 'ASIN';
    public const ATAN       = 'ATAN';
    public const ATAN2      = 'ATAN2';
    public const EOF        = 'EOF';

    private string $type;
    private string $value;
    private int    $start;
    private int    $end;

    public function __construct(string $type, string $value, int $start, int $end)
    {
        $this->type  = $type;
        $this->value = $value;
        $this->start = $start;
        $this->end   = $end;
    }

    public function getType(): string  { return $this->type; }
    public function getValue(): string { return $this->value; }
    public function getStart(): int    { return $this->start; }
    public function getEnd(): int      { return $this->end; }
}
