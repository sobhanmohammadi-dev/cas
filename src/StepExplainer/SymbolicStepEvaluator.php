<?php
namespace CAS\StepExplainer;

use CAS\Nodes\MathNode;
use CAS\Services\{Simplifier, SimplifierObserver, SymbolTable};

/**
 * Evaluates a math node symbolically while recording each simplification
 * step applied by the Simplifier via the SimplifierObserver interface.
 *
 * This replaces the previous implementation that duplicated the entire
 * Simplifier rule-set. All rule logic now lives exclusively in Simplifier.
 */
class SymbolicStepEvaluator implements SimplifierObserver
{
    private SymbolTable  $symbolTable;
    private Simplifier   $simplifier;
    private StepRecorder $recorder;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->simplifier  = new Simplifier($symbolTable);
        $this->recorder    = new StepRecorder();

        // Wire ourselves as the observer so every rule application is captured
        $this->simplifier->setObserver($this);
    }

    // ─── SimplifierObserver ───────────────────────────────────────────

    public function onRuleApplied(string $ruleName, MathNode $before, MathNode $after): void
    {
        $this->recorder->record(
            StepExplainer::algebraicRuleApplied($ruleName, (string) $before, (string) $after)
        );
    }

    // ─── Public API ───────────────────────────────────────────────────

    /**
     * Simplify $node symbolically and return the result.
     * Call getSteps() afterwards to retrieve the recorded steps.
     */
    public function evaluate(MathNode $node): MathNode
    {
        $this->recorder->reset();
        return $this->simplifier->simplifyFully($node);
    }

    /** @return StepText[] */
    public function getSteps(): array
    {
        return $this->recorder->getSteps();
    }
}
