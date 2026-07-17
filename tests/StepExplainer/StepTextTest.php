<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\StepExplainer\StepText;

final class StepTextTest extends TestCase
{
    public function testAccessors(): void
    {
        $t = new StepText('english', 'persian', 'formula', 'calc');
        $this->assertSame('english', $t->getEn());
        $this->assertSame('persian', $t->getFa());
        $this->assertSame('formula', $t->getFormula());
        $this->assertSame('calc', $t->getCalculation());
    }
}
