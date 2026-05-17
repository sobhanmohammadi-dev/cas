<?php
namespace CAS\StepExplainer;

class StepRecorder
{
    private array $steps = [];

    public function record(StepText $step): void
    {
        $this->steps[] = $step;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}