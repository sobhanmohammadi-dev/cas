<?php
namespace CAS\StepExplainer;

class StepRecorder
{
    /** @var StepText[] */
    private array $steps = [];

    public function record(StepText $step): void
    {
        $this->steps[] = $step;
    }

    /** @return StepText[] */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function reset(): void
    {
        $this->steps = [];
    }
}
