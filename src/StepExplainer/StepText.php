<?php
namespace CAS\StepExplainer;

final class StepText
{
    private string $en;
    private string $fa;
    private string $formula;
    private string $calculation;

    public function __construct(string $en, string $fa, string $formula, string $calculation)
    {
        $this->en = $en;
        $this->fa = $fa;
        $this->formula = $formula;
        $this->calculation = $calculation;
    }

    public function getEn(): string { return $this->en; }
    public function getFa(): string { return $this->fa; }
    public function getFormula(): string { return $this->formula; }
    public function getCalculation(): string { return $this->calculation; }
}