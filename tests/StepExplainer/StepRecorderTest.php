<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\StepExplainer\{StepRecorder, StepText};

final class StepRecorderTest extends TestCase
{
    public function testRecordAndGetSteps(): void
    {
        $r = new StepRecorder();
        $this->assertSame([], $r->getSteps());

        $step = new StepText('a', 'b', 'c', 'd');
        $r->record($step);
        $this->assertSame([$step], $r->getSteps());
    }

    public function testReset(): void
    {
        $r = new StepRecorder();
        $r->record(new StepText('a', 'b', 'c', 'd'));
        $r->reset();
        $this->assertSame([], $r->getSteps());
    }

    public function testMultipleStepsPreserveOrder(): void
    {
        $r = new StepRecorder();
        $s1 = new StepText('1', '', '', '');
        $s2 = new StepText('2', '', '', '');
        $r->record($s1);
        $r->record($s2);
        $this->assertSame([$s1, $s2], $r->getSteps());
    }
}
