<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\MathNode;

final class MathNodeTest extends TestCase
{
    private function makeConcrete(int $s, int $e): MathNode
    {
        return new class($s, $e) extends MathNode {
            public function __toString(): string { return 'concrete'; }
        };
    }

    public function testPositionsAreStored(): void
    {
        $node = $this->makeConcrete(3, 7);
        $this->assertSame(3, $node->getStartPos());
        $this->assertSame(7, $node->getEndPos());
    }

    public function testToMathStringDelegatesToToStringByDefault(): void
    {
        $node = $this->makeConcrete(0, 0);
        $this->assertSame('concrete', $node->toMathString());
        $this->assertSame((string) $node, $node->toMathString());
    }
}
