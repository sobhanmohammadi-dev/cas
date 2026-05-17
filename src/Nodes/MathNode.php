<?php
namespace CAS\Nodes;

abstract class MathNode {
    protected int $startPos;
    protected int $endPos;

    public function __construct(int $s, int $e) {
        $this->startPos = $s;
        $this->endPos = $e;
    }

    public function getStartPos(): int {
        return $this->startPos;
    }

    public function getEndPos(): int {
        return $this->endPos;
    }

    abstract public function __toString(): string;
}
