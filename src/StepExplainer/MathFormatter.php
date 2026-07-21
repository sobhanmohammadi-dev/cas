<?php

namespace Sobhanmohammadi\CAS\StepExplainer;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode, PowerNode,
    UnaryNode, SqrtNode, RootNode, PiNode, VariableNode,
    BinaryOperatorNode,
    TrigFunctionNode, SinNode, CosNode, TanNode, AsinNode, AtanNode, Atan2Node
};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\Exception\DomainException;

/**
 * MathFormatter
 *
 * Evaluates a numeric expression step-by-step and returns a structured array:
 *
 *   [
 *     'title'      => string,
 *     'expression' => string,
 *     'steps'      => [
 *       [
 *         'step'        => int,
 *         'operation'   => string,
 *         'target'      => string,
 *         'before'      => string,   // full expression BEFORE this step
 *         'after'       => string,   // full expression AFTER this step
 *         'formula'     => string,
 *         'calculation' => string,
 *         'explanation' => string,
 *       ], ...
 *     ],
 *     'result' => ['value' => int|float|string, 'expression' => string],
 *   ]
 *
 * Language: 'en' (English) or 'fa' (Persian / Farsi).
 *
 * Design
 * ──────
 * - The AST is walked in natural evaluation order (respecting operator precedence
 *   since PHP's parser already encoded it in the tree).
 * - Every evaluated node is registered in $this->values[spl_object_id($node)].
 * - renderState() re-walks the *original* root at any moment: if a node's
 *   object-id is in $values it prints the numeric result, otherwise it
 *   recurses with prettyRender(). This gives correct before/after strings
 *   because only the nodes computed SO FAR have entries.
 */
class MathFormatter
{
    private SymbolTable $symbolTable;
    private string      $lang;
    private int         $scale;

    /** Original root node — never mutated */
    private MathNode $root;

    /**
     * spl_object_id(node) => numeric string result
     * Populated incrementally as each sub-expression is evaluated.
     * @var array<int,string>
     */
    private array $values = [];

    /** Collected steps */
    private array $steps = [];

    // ── Translation tables ────────────────────────────────────────────────────

    private const LABELS = [
        'en' => [
            'title'          => 'Simplifying an Algebraic Expression',
            'Power'          => 'Power',
            'Square Root'    => 'Square Root',
            'Nth Root'       => 'Nth Root',
            'Multiplication' => 'Multiplication',
            'Division'       => 'Division',
            'Addition'       => 'Addition',
            'Subtraction'    => 'Subtraction',
            'Negation'       => 'Negation',
        ],
        'fa' => [
            'title'          => 'ساده‌سازی یک عبارت جبری',
            'Power'          => 'توان',
            'Square Root'    => 'رادیکال',
            'Nth Root'       => 'ریشه n-ام',
            'Multiplication' => 'ضرب',
            'Division'       => 'تقسیم',
            'Addition'       => 'جمع',
            'Subtraction'    => 'تفریق',
            'Negation'       => 'منفی',
        ],
    ];

    private const FORMULAS = [
        'en' => [
            'Power'          => 'a^n = a × a × ... × a (n times)',
            'Power2'         => 'a² = a × a',
            'Power3'         => 'a³ = a × a × a',
            'Square Root'    => '√a = b if b² = a',
            'Nth Root'       => 'ⁿ√a = a^(1/n)',
            'Multiplication' => 'a × b = product',
            'Division'       => 'a ÷ b = quotient',
            'Addition'       => 'a + b = sum',
            'Subtraction'    => 'a - b = difference',
            'Negation'       => '-a',
        ],
        'fa' => [
            'Power'          => 'a^n = a × a × ... × a',
            'Power2'         => 'a² = a × a',
            'Power3'         => 'a³ = a × a × a',
            'Square Root'    => '√a = b اگر b² = a',
            'Nth Root'       => 'ⁿ√a = a^(1/n)',
            'Multiplication' => 'a × b = حاصل‌ضرب',
            'Division'       => 'a ÷ b = خارج قسمت',
            'Addition'       => 'a + b = مجموع',
            'Subtraction'    => 'a - b = تفاضل',
            'Negation'       => '-a',
        ],
    ];

    private const EXPLANATIONS = [
        'en' => [
            'Power'          => 'Calculate the exponent because powers have higher priority than multiplication and division.',
            'Power2'         => 'Compute the square of the base.',
            'Power3'         => 'Compute the cube of the base.',
            'Square Root'    => 'Compute the square root before continuing with the remaining operations.',
            'Nth Root'       => 'Compute the nth root of the radicand.',
            'Multiplication' => 'Perform the multiplication, working left to right.',
            'Division'       => 'Perform the division.',
            'Addition'       => 'Add the values.',
            'Subtraction'    => 'Subtract the value to get the final result.',
            'Negation'       => 'Apply unary negation.',
            'final_en'       => 'Finally, perform the last operation to obtain the result.',
        ],
        'fa' => [
            'Power'          => 'ابتدا توان را محاسبه می‌کنیم، زیرا توان اولویت بیشتری نسبت به عملیات دیگر دارد.',
            'Power2'         => 'مربع پایه را محاسبه می‌کنیم.',
            'Power3'         => 'مکعب پایه را محاسبه می‌کنیم.',
            'Square Root'    => 'مقدار ریشه دوم را محاسبه می‌کنیم.',
            'Nth Root'       => 'ریشه n-ام مقدار را محاسبه می‌کنیم.',
            'Multiplication' => 'عملیات ضرب را انجام می‌دهیم.',
            'Division'       => 'تقسیم را انجام می‌دهیم.',
            'Addition'       => 'اعداد را با هم جمع می‌کنیم.',
            'Subtraction'    => 'عدد را کم می‌کنیم.',
            'Negation'       => 'عدد را منفی می‌کنیم.',
            'final_fa'       => 'در مرحله آخر عملیات را انجام داده و جواب نهایی را به دست می‌آوریم.',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(SymbolTable $symbolTable, string $lang = 'en', int $scale = 10)
    {
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('BCMath extension is required for MathFormatter.');
        }
        $this->symbolTable = $symbolTable;
        $this->lang        = in_array($lang, ['en', 'fa'], true) ? $lang : 'en';
        $this->scale       = $scale;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate $expression and return the step-by-step structured array.
     *
     * @param  string $expression  CAS syntax: "((2^3 * sqrt(36)) / 4) + 5^2"
     * @return array
     */
    public function format(string $expression): array
    {
        $this->steps  = [];
        $this->values = [];

        $lexer        = new Lexer($expression);
        $parser       = new Parser($lexer->tokenize(), $expression);
        $this->root   = $parser->parse();

        $prettyOrig   = $this->prettyRender($this->root, 0);
        $finalValue   = $this->walk($this->root);
        $finalStr     = $this->fmt($finalValue);

        // Override the last step's explanation with a "final" sentence
        if (!empty($this->steps)) {
            $li = count($this->steps) - 1;
            $key = $this->lang === 'fa' ? 'final_fa' : 'final_en';
            $this->steps[$li]['explanation'] =
                self::EXPLANATIONS[$this->lang][$key]
                ?? $this->steps[$li]['explanation'];
        }

        return [
            'title'      => self::LABELS[$this->lang]['title'],
            'expression' => $prettyOrig,
            'steps'      => $this->steps,
            'result'     => [
                'value'      => is_numeric($finalStr) ? $finalStr + 0 : $finalStr,
                'expression' => $finalStr,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Walker
    // ─────────────────────────────────────────────────────────────────────────

    private function walk(MathNode $node): string
    {
        // Numeric atoms — store and return (no step)
        if ($node instanceof IntegerNode) {
            $v = \gmp_strval($node->getValue());
            $this->store($node, $v);
            return $v;
        }
        if ($node instanceof RationalNode) {
            $v = bcdiv(
                \gmp_strval($node->getValueOfNumerator()),
                \gmp_strval($node->getValueOfDenominator()),
                $this->scale
            );
            $this->store($node, $v);
            return $v;
        }
        if ($node instanceof PiNode) {
            $v = $this->piValue();
            $this->store($node, $v);
            return $v;
        }
        if ($node instanceof VariableNode) {
            $bound = $this->symbolTable->lookup($node->getName());
            if ($bound === null) {
                throw new \RuntimeException("Undefined variable '{$node->getName()}'.");
            }
            $v = $this->walk($bound);
            $this->store($node, $v);
            return $v;
        }

        // Unary minus
        if ($node instanceof UnaryNode) {
            if ($node->getOp() !== '-') {
                throw new \RuntimeException("Unsupported unary op '{$node->getOp()}'.");
            }
            $innerVal = $this->walk($node->getOperand());
            $before   = $this->renderState();
            $inner    = $this->fmt($innerVal);
            $result   = bcmul('-1', $innerVal, $this->scale);
            $res      = $this->fmt($result);
            $this->store($node, $result);
            $after  = $this->renderState();
            $target = '-' . $inner;
            $this->addStep('Negation', 'Negation', $target, $before, $after,
                $target . ' = ' . $res);
            return $result;
        }

        if ($node instanceof PowerNode)    { return $this->walkPower($node); }
        if ($node instanceof SqrtNode)     { return $this->walkSqrt($node); }
        if ($node instanceof RootNode)     { return $this->walkRoot($node); }
        if ($node instanceof Atan2Node)    { return $this->walkAtan2($node); }
        if ($node instanceof TrigFunctionNode) { return $this->walkTrig($node); }
        if ($node instanceof MultiplyNode) { return $this->walkBinary($node, 'Multiplication', '×'); }
        if ($node instanceof DivideNode)   { return $this->walkBinary($node, 'Division',       '÷'); }
        if ($node instanceof PlusNode)     { return $this->walkBinary($node, 'Addition',       '+'); }
        if ($node instanceof MinusNode)    { return $this->walkBinary($node, 'Subtraction',    '-'); }

        throw new \RuntimeException('Unsupported node: ' . get_class($node));
    }

    private function walkTrig(TrigFunctionNode $node): string
    {
        $argVal = $this->walk($node->getArgument());
        $before = $this->renderState();
        $arg    = $this->fmt($argVal);
        $fnName = $node->getFunctionName();

        $argFloat = (float) $argVal;
        if ($node instanceof AsinNode && ($argFloat < -1.0 || $argFloat > 1.0)) {
            throw new DomainException("asin({$arg}) is undefined: argument must be in [-1, 1].");
        }

        $value = match (true) {
            $node instanceof SinNode  => sin($argFloat),
            $node instanceof CosNode  => cos($argFloat),
            $node instanceof TanNode  => tan($argFloat),
            $node instanceof AsinNode => asin($argFloat),
            $node instanceof AtanNode => atan($argFloat),
            default => throw new \RuntimeException('Unknown trig node: ' . get_class($node)),
        };
        if (is_nan($value) || is_infinite($value)) {
            throw new DomainException("{$fnName}({$arg}) is undefined or unbounded.");
        }

        $result = $this->floatToBc($value);
        $res    = $this->fmt($result);
        $target = $fnName . '(' . $arg . ')';
        $calc   = $target . ' = ' . $res;

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep('Trigonometric Function', 'Trig', $target, $before, $after, $calc);
        return $result;
    }

    private function walkAtan2(Atan2Node $node): string
    {
        $yVal   = $this->walk($node->getY());
        $xVal   = $this->walk($node->getX());
        $before = $this->renderState();
        $y      = $this->fmt($yVal);
        $x      = $this->fmt($xVal);

        if ((float) $yVal === 0.0 && (float) $xVal === 0.0) {
            throw new DomainException('atan2(0, 0) is undefined.');
        }

        $result = $this->floatToBc(atan2((float) $yVal, (float) $xVal));
        $res    = $this->fmt($result);
        $target = 'atan2(' . $y . ', ' . $x . ')';
        $calc   = $target . ' = ' . $res;

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep('Two-Argument Arctangent', 'Atan2', $target, $before, $after, $calc);
        return $result;
    }

    private function walkPower(PowerNode $node): string
    {
        // Walk children first so their values are resolved before we capture 'before'
        $baseVal = $this->walk($node->getLeft());
        $expVal  = $this->walk($node->getRight());
        $before  = $this->renderState();
        $base    = $this->fmt($baseVal);
        $exp     = $this->fmt($expVal);

        if (preg_match('/^[+-]?\d+$/', $expVal)) {
            $result = bcpow($baseVal, $expVal, $this->scale);
        } else {
            $f = pow((float) $baseVal, (float) $expVal);
            $result = $this->floatToBc($f);
        }
        $res = $this->fmt($result);

        $fmlKey = 'Power';
        if ($exp === '2') $fmlKey = 'Power2';
        elseif ($exp === '3') $fmlKey = 'Power3';

        $target = $base . '^' . $exp;
        $calc   = $this->powerCalcStr($base, $exp, $res);

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep('Power', $fmlKey, $target, $before, $after, $calc);
        return $result;
    }

    private function walkSqrt(SqrtNode $node): string
    {
        $radVal = $this->walk($node->getRadicand());
        $before = $this->renderState();
        $rad    = $this->fmt($radVal);

        if (bccomp($radVal, '0', $this->scale) < 0) {
            throw new \RuntimeException("Square root of negative number: {$rad}");
        }
        $result = bcsqrt($radVal, $this->scale);
        $res    = $this->fmt($result);

        $target = '√' . $rad;
        $calc   = $target . ' = ' . $res;

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep('Square Root', 'Square Root', $target, $before, $after, $calc);
        return $result;
    }

    private function walkRoot(RootNode $node): string
    {
        $degVal = $this->walk($node->getDegree());
        $radVal = $this->walk($node->getRadicand());
        $before = $this->renderState();
        $deg    = $this->fmt($degVal);
        $rad    = $this->fmt($radVal);

        $result = bcpow($radVal, bcdiv('1', $degVal, $this->scale), $this->scale);
        $res    = $this->fmt($result);

        $target = $this->rootPrefix($deg) . '√' . $rad;
        $calc   = $target . ' = ' . $res;

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep('Nth Root', 'Nth Root', $target, $before, $after, $calc);
        return $result;
    }

    private function walkBinary(BinaryOperatorNode $node, string $opKey, string $sym): string
    {
        $leftVal  = $this->walk($node->getLeft());
        $rightVal = $this->walk($node->getRight());
        $left     = $this->fmt($leftVal);
        $right    = $this->fmt($rightVal);
        // Capture 'before' after children are resolved so it shows their computed values
        $before   = $this->renderState();

        switch ($opKey) {
            case 'Multiplication':
                $result = bcmul($leftVal, $rightVal, $this->scale);
                break;
            case 'Division':
                if (bccomp($rightVal, '0', $this->scale) === 0) {
                    throw new \RuntimeException('Division by zero.');
                }
                $result = bcdiv($leftVal, $rightVal, $this->scale);
                break;
            case 'Addition':
                $result = bcadd($leftVal, $rightVal, $this->scale);
                break;
            case 'Subtraction':
                $result = bcsub($leftVal, $rightVal, $this->scale);
                break;
            default:
                throw new \RuntimeException("Unknown op: {$opKey}");
        }

        $res    = $this->fmt($result);
        $target = $left . ' ' . $sym . ' ' . $right;
        $calc   = $target . ' = ' . $res;

        $this->store($node, $result);
        $after = $this->renderState();
        $this->addStep($opKey, $opKey, $target, $before, $after, $calc);
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  State renderer
    //  Re-renders the original root, substituting evaluated nodes with numbers.
    // ─────────────────────────────────────────────────────────────────────────

    private function store(MathNode $node, string $value): void
    {
        $this->values[spl_object_id($node)] = $value;
    }

    /**
     * Render the expression's current state.
     * Already-evaluated nodes appear as their numeric result.
     * Pending nodes are pretty-printed from their AST structure.
     */
    private function renderState(): string
    {
        return $this->renderNode($this->root, 0);
    }

    private function renderNode(MathNode $node, int $parentPrec): string
    {
        $id = spl_object_id($node);
        if (isset($this->values[$id])) {
            return $this->fmt($this->values[$id]);
        }
        // Node not yet evaluated — recurse into children
        return $this->prettyRenderPartial($node, $parentPrec);
    }

    /**
     * Like prettyRender() but uses renderNode() for children so that
     * already-computed sub-trees show their numeric values.
     */
    private function prettyRenderPartial(MathNode $node, int $parentPrec): string
    {
        if ($node instanceof IntegerNode) {
            return \gmp_strval($node->getValue());
        }
        if ($node instanceof RationalNode) {
            $n = \gmp_strval($node->getValueOfNumerator());
            $d = \gmp_strval($node->getValueOfDenominator());
            return $d === '1' ? $n : "({$n}/{$d})";
        }
        if ($node instanceof PiNode)       { return 'π'; }
        if ($node instanceof VariableNode) { return $node->getName(); }

        if ($node instanceof UnaryNode && $node->getOp() === '-') {
            $inner = $this->renderNode($node->getOperand(), 4);
            $s = '-' . $inner;
            return $parentPrec >= 4 ? "({$s})" : $s;
        }
        if ($node instanceof PowerNode) {
            $base = $this->renderNode($node->getLeft(),  3);
            $exp  = $this->renderNode($node->getRight(), 4);
            return $base . '^' . $exp;
        }
        if ($node instanceof SqrtNode) {
            $rad = $this->renderNode($node->getRadicand(), 5);
            return '√' . $rad;
        }
        if ($node instanceof RootNode) {
            $deg = $this->renderNode($node->getDegree(),   5);
            $rad = $this->renderNode($node->getRadicand(), 5);
            return $this->rootPrefix($this->fmt($deg)) . '√' . $rad;
        }
        if ($node instanceof Atan2Node) {
            $y = $this->renderNode($node->getY(), 0);
            $x = $this->renderNode($node->getX(), 0);
            return 'atan2(' . $y . ', ' . $x . ')';
        }
        if ($node instanceof TrigFunctionNode) {
            $arg = $this->renderNode($node->getArgument(), 0);
            return $node->getFunctionName() . '(' . $arg . ')';
        }
        if ($node instanceof MultiplyNode) {
            $l = $this->renderNode($node->getLeft(),  2);
            $r = $this->renderNode($node->getRight(), 3);
            $s = $l . ' × ' . $r;
            return $parentPrec >= 2 ? "({$s})" : $s;
        }
        if ($node instanceof DivideNode) {
            $l = $this->renderNode($node->getLeft(),  2);
            $r = $this->renderNode($node->getRight(), 3);
            $s = $l . ' ÷ ' . $r;
            return $parentPrec >= 2 ? "({$s})" : $s;
        }
        if ($node instanceof PlusNode) {
            $l = $this->renderNode($node->getLeft(),  1);
            $r = $this->renderNode($node->getRight(), 1);
            $s = $l . ' + ' . $r;
            return $parentPrec > 1 ? "({$s})" : $s;
        }
        if ($node instanceof MinusNode) {
            $l = $this->renderNode($node->getLeft(),  1);
            $r = $this->renderNode($node->getRight(), 2);
            $s = $l . ' - ' . $r;
            return $parentPrec > 1 ? "({$s})" : $s;
        }
        return (string) $node;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Pure pretty printer (no partial-evaluation awareness)
    // ─────────────────────────────────────────────────────────────────────────

    private function prettyRender(MathNode $node, int $parentPrec): string
    {
        if ($node instanceof IntegerNode) { return \gmp_strval($node->getValue()); }
        if ($node instanceof RationalNode) {
            $n = \gmp_strval($node->getValueOfNumerator());
            $d = \gmp_strval($node->getValueOfDenominator());
            return $d === '1' ? $n : "({$n}/{$d})";
        }
        if ($node instanceof PiNode)       { return 'π'; }
        if ($node instanceof VariableNode) { return $node->getName(); }
        if ($node instanceof UnaryNode && $node->getOp() === '-') {
            $inner = $this->prettyRender($node->getOperand(), 4);
            $s = '-' . $inner;
            return $parentPrec >= 4 ? "({$s})" : $s;
        }
        if ($node instanceof PowerNode) {
            $base = $this->prettyRender($node->getLeft(), 3);
            $exp  = $this->prettyRender($node->getRight(), 4);
            return $base . '^' . $exp;
        }
        if ($node instanceof SqrtNode) {
            return '√' . $this->prettyRender($node->getRadicand(), 5);
        }
        if ($node instanceof RootNode) {
            $deg = $this->prettyRender($node->getDegree(), 5);
            $rad = $this->prettyRender($node->getRadicand(), 5);
            return $this->rootPrefix($deg) . '√' . $rad;
        }
        if ($node instanceof Atan2Node) {
            $y = $this->prettyRender($node->getY(), 0);
            $x = $this->prettyRender($node->getX(), 0);
            return 'atan2(' . $y . ', ' . $x . ')';
        }
        if ($node instanceof TrigFunctionNode) {
            $arg = $this->prettyRender($node->getArgument(), 0);
            return $node->getFunctionName() . '(' . $arg . ')';
        }
        if ($node instanceof MultiplyNode) {
            $l = $this->prettyRender($node->getLeft(), 2);
            $r = $this->prettyRender($node->getRight(), 3);
            $s = $l . ' × ' . $r;
            return $parentPrec >= 2 ? "({$s})" : $s;
        }
        if ($node instanceof DivideNode) {
            $l = $this->prettyRender($node->getLeft(), 2);
            $r = $this->prettyRender($node->getRight(), 3);
            $s = $l . ' ÷ ' . $r;
            return $parentPrec >= 2 ? "({$s})" : $s;
        }
        if ($node instanceof PlusNode) {
            $l = $this->prettyRender($node->getLeft(), 1);
            $r = $this->prettyRender($node->getRight(), 1);
            $s = $l . ' + ' . $r;
            return $parentPrec > 1 ? "({$s})" : $s;
        }
        if ($node instanceof MinusNode) {
            $l = $this->prettyRender($node->getLeft(), 1);
            $r = $this->prettyRender($node->getRight(), 2);
            $s = $l . ' - ' . $r;
            return $parentPrec > 1 ? "({$s})" : $s;
        }
        return (string) $node;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Step assembler
    // ─────────────────────────────────────────────────────────────────────────

    private function addStep(
        string $opKey,
        string $fmlKey,
        string $target,
        string $before,
        string $after,
        string $calc
    ): void {
        $lang    = $this->lang;
        $stepNum = count($this->steps) + 1;

        $this->steps[] = [
            'step'        => $stepNum,
            'operation'   => self::LABELS[$lang][$opKey]    ?? $opKey,
            'target'      => $target,
            'before'      => $before,
            'after'       => $after,
            'formula'     => self::FORMULAS[$lang][$fmlKey] ?? '',
            'calculation' => $calc,
            'explanation' => self::EXPLANATIONS[$lang][$fmlKey]
                             ?? self::EXPLANATIONS[$lang][$opKey]
                             ?? '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Utilities
    // ─────────────────────────────────────────────────────────────────────────

    /** Trim trailing bcmath zeros. "8.0000" → "8", "0.5000" → "0.5" */
    private function fmt(string $value): string
    {
        if (strpos($value, '.') !== false) {
            $value = rtrim($value, '0');
            $value = rtrim($value, '.');
        }
        return $value;
    }

    private function floatToBc(float $f): string
    {
        if (is_nan($f) || is_infinite($f)) {
            throw new \RuntimeException('Result is NaN or Infinite.');
        }
        return number_format($f, $this->scale, '.', '');
    }

    private function piValue(): string
    {
        $pi = '3.14159265358979323846264338327950288419716939937510';
        return substr($pi, 0, $this->scale + 2);
    }

    /** Build a readable calculation string for a^n. */
    private function powerCalcStr(string $base, string $exp, string $result): string
    {
        static $sup = ['0'=>'⁰','1'=>'¹','2'=>'²','3'=>'³','4'=>'⁴',
                       '5'=>'⁵','6'=>'⁶','7'=>'⁷','8'=>'⁸','9'=>'⁹'];
        $superExp = '';
        foreach (str_split($exp) as $ch) {
            $superExp .= $sup[$ch] ?? $ch;
        }
        $lhs    = $base . $superExp;
        $expInt = (int) $exp;
        if ($expInt >= 2 && $expInt <= 6 && preg_match('/^\d+$/', $exp)) {
            $factors = implode(' × ', array_fill(0, $expInt, $base));
            return $lhs . ' = ' . $factors . ' = ' . $result;
        }
        return $lhs . ' = ' . $result;
    }

    /** Superscript prefix for nth-root (empty for square root). */
    private function rootPrefix(string $deg): string
    {
        if ($deg === '2') return '';
        if ($deg === '3') return '∛';
        static $sup = ['0'=>'⁰','1'=>'¹','2'=>'²','3'=>'³','4'=>'⁴',
                       '5'=>'⁵','6'=>'⁶','7'=>'⁷','8'=>'⁸','9'=>'⁹'];
        $out = '';
        foreach (str_split($deg) as $ch) { $out .= $sup[$ch] ?? $ch; }
        return $out;
    }
}
