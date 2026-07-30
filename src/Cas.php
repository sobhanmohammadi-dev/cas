<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS;

use Sobhanmohammadi\CAS\Evaluation\ExactEvaluator;
use Sobhanmohammadi\CAS\Evaluation\NumericEvaluator;
use Sobhanmohammadi\CAS\Evaluation\NumericStepEvaluator;
use Sobhanmohammadi\CAS\Evaluation\SymbolTable;
use Sobhanmohammadi\CAS\Explain\LocalizationExtractor;
use Sobhanmohammadi\CAS\Explain\SimplificationTrace;
use Sobhanmohammadi\CAS\Explain\StepDocument;
use Sobhanmohammadi\CAS\Explain\Translator;
use Sobhanmohammadi\CAS\Node\EquationNode;
use Sobhanmohammadi\CAS\Node\Node;
use Sobhanmohammadi\CAS\Number\Rational;
use Sobhanmohammadi\CAS\Parsing\Parser;
use Sobhanmohammadi\CAS\Simplification\Simplifier;
use Sobhanmohammadi\CAS\Solving\EquationSolver;
use Sobhanmohammadi\CAS\Solving\Solution;
use Sobhanmohammadi\CAS\Solving\SolvedEquation;

/**
 * Convenience facade for the most common operations. For finer-grained
 * control, use Parser/Simplifier/EquationSolver/ExactEvaluator/
 * NumericEvaluator/NumericStepEvaluator directly.
 */
final class Cas
{
    private readonly Parser $parser;
    private readonly Simplifier $simplifier;
    private readonly ExactEvaluator $exactEvaluator;
    private readonly NumericEvaluator $numericEvaluator;
    private readonly NumericStepEvaluator $numericStepEvaluator;
    private readonly EquationSolver $solver;
    private readonly LocalizationExtractor $localizationExtractor;
    private readonly Translator $translator;

    public function __construct()
    {
        $this->parser = new Parser();
        $this->simplifier = new Simplifier();
        $this->exactEvaluator = new ExactEvaluator();
        $this->numericEvaluator = new NumericEvaluator();
        $this->numericStepEvaluator = new NumericStepEvaluator();
        $this->solver = new EquationSolver();
        $this->localizationExtractor = new LocalizationExtractor();
        $this->translator = new Translator();
    }

    public function parse(string $expression): Node
    {
        return $this->parser->parse($expression);
    }

    public function simplify(string|Node $expression): Node
    {
        return $this->simplifier->simplify($this->toNode($expression));
    }

    /** Simplifies with a full narrated trace: expansion, like-term collection, identities, folding. */
    public function simplifyWithSteps(string|Node $expression): SimplificationTrace
    {
        return $this->simplifier->simplifyWithSteps($this->toNode($expression));
    }

    public function evaluateExact(string|Node $expression, SymbolTable $symbols = new SymbolTable()): Rational
    {
        return $this->exactEvaluator->evaluate($this->toNode($expression), $symbols);
    }

    public function evaluateNumeric(string|Node $expression, SymbolTable $symbols = new SymbolTable()): float
    {
        return $this->numericEvaluator->evaluate($this->toNode($expression), $symbols);
    }

    /** Evaluates numerically one operation at a time in precedence order, with full narration. */
    public function evaluateNumericWithSteps(string|Node $expression, SymbolTable $symbols = new SymbolTable()): StepDocument
    {
        return $this->numericStepEvaluator->evaluateWithSteps($this->toNode($expression), $symbols);
    }

    public function solveFor(string|Node $equation, string $variable): Solution
    {
        return $this->solver->solve($this->toEquation($equation), $variable);
    }

    /** Solves with a full narrated trace, including radical isolation/squaring/verification when needed. */
    public function solveForWithSteps(string|Node $equation, string $variable): SolvedEquation
    {
        return $this->solver->solveWithSteps($this->toEquation($equation), $variable);
    }

    /**
     * Collects every translatable narration key/default-English-text pair
     * from a StepDocument, ready to hand to a translator.
     *
     * @return array<string,string>
     */
    public function extractLocalizationCatalog(StepDocument $document): array
    {
        return $this->localizationExtractor->extract($document);
    }

    /**
     * Rebuilds a StepDocument with a language pack applied. Mathematical
     * content (expressions, numbers) is left untouched; only narration
     * (titles, rules, formulas) is translated.
     *
     * @param array<string,string> $languagePack key => localized text
     */
    public function translate(StepDocument $document, array $languagePack): StepDocument
    {
        return $this->translator->translate($document, $languagePack);
    }

    private function toEquation(string|Node $equation): EquationNode
    {
        $node = $this->toNode($equation);
        if (!$node instanceof EquationNode) {
            throw new Exception\InvalidExpressionException('This operation requires an equation containing "=".');
        }
        return $node;
    }

    private function toNode(string|Node $expression): Node
    {
        return is_string($expression) ? $this->parser->parse($expression) : $expression;
    }
}
