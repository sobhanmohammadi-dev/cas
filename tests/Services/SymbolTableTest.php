<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\Nodes\IntegerNode;

final class SymbolTableTest extends TestCase
{
    public function testAssignAndLookup(): void
    {
        $t = new SymbolTable();
        $val = new IntegerNode('5', 0, 1);
        $t->assign('x', $val);
        $this->assertSame($val, $t->lookup('x'));
    }

    public function testLookupUnknownReturnsNull(): void
    {
        $t = new SymbolTable();
        $this->assertNull($t->lookup('nope'));
    }

    public function testIsAssigned(): void
    {
        $t = new SymbolTable();
        $this->assertFalse($t->isAssigned('x'));
        $t->assign('x', new IntegerNode('1', 0, 1));
        $this->assertTrue($t->isAssigned('x'));
    }

    public function testRemove(): void
    {
        $t = new SymbolTable();
        $t->assign('x', new IntegerNode('1', 0, 1));
        $t->remove('x');
        $this->assertFalse($t->isAssigned('x'));
        $this->assertNull($t->lookup('x'));
    }

    public function testAll(): void
    {
        $t = new SymbolTable();
        $a = new IntegerNode('1', 0, 1);
        $b = new IntegerNode('2', 0, 1);
        $t->assign('x', $a);
        $t->assign('y', $b);
        $this->assertSame(['x' => $a, 'y' => $b], $t->all());
    }
}
