<?php
namespace Sobhanmohammadi\CAS\Tests\Nodes;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Nodes\{IntegerNode, SqrtNode, RootNode};

final class RadicalNodesTest extends TestCase
{
    public function testSqrtNode(): void
    {
        $rad = new IntegerNode('16', 0, 2);
        $n = new SqrtNode($rad, 0, 3);
        $this->assertSame($rad, $n->getRadicand());
        $this->assertSame('sqrt(16)', (string) $n);
    }

    public function testRootNodeDegreeFirstRadicandSecond(): void
    {
        $deg = new IntegerNode('3', 0, 1);
        $rad = new IntegerNode('27', 2, 4);
        $n = new RootNode($deg, $rad, 0, 5);
        // Constructor order is degree, radicand -- verify no accidental swap.
        $this->assertSame($deg, $n->getDegree());
        $this->assertSame($rad, $n->getRadicand());
        $this->assertSame('root(3, 27)', (string) $n);
    }
}
