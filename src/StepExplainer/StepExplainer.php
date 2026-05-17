<?php
namespace CAS\StepExplainer;

final class StepExplainer
{
    private static function build(string $key, array $replacements): StepText
    {
        if (!isset(Texts::$messages[$key])) {
            throw new \InvalidArgumentException("Unknown message key: {$key}");
        }
        $msg = Texts::$messages[$key];
        $en = self::replace($msg['en'] ?? '', $replacements);
        $fa = self::replace($msg['fa'] ?? '', $replacements);
        $formula = self::replace($msg['formula'] ?? '', $replacements);
        $calculation = self::replace($msg['calculation'] ?? '', $replacements);
        return new StepText($en, $fa, $formula, $calculation);
    }

    private static function replace(string $template, array $vars): string
    {
        $search = array_map(static function($k) {
            return '{' . $k . '}';
        }, array_keys($vars));
        return str_replace($search, array_values($vars), $template);
    }

    public static function piSubstitution(string $decFmt): StepText
    {
        return self::build('piSubstitution', ['decFmt' => $decFmt]);
    }

    public static function variableSubstitution(string $varName, string $valFmt): StepText
    {
        return self::build('variableSubstitution', [
            'varName' => $varName,
            'valFmt'  => $valFmt,
        ]);
    }

    public static function mergedLeafOperation(
        array  $substitutions,
        string $originalExpr,
        string $instantiated,
        string $opName,
        string $opNameFa,
        string $resFmt
    ): StepText {
        $subList = implode(', ', array_map(
            static function($k, $v) {
                return "{$k} = {$v}";
            },
            array_keys($substitutions),
            array_values($substitutions)
        ));
        return self::build('mergedLeafOperation', [
            'substitutions' => $subList,
            'originalExpr'  => $originalExpr,
            'instantiated'  => $instantiated,
            'resFmt'        => $resFmt,
        ]);
    }

    public static function constantChain(string $exprStr, string $resFmt): StepText
    {
        return self::build('constantChain', [
            'exprStr' => $exprStr,
            'resFmt'  => $resFmt,
        ]);
    }

    public static function unaryNegation(string $origFmt, string $resFmt): StepText
    {
        return self::build('unaryNegation', [
            'origFmt' => $origFmt,
            'resFmt'  => $resFmt,
        ]);
    }

    public static function sqrtOperation(
        string $aFmt,
        string $vFmt,
        bool $perfect,
        int $precision,
        string $vRoundedFmt
    ): StepText {
        $key = $perfect ? 'sqrt_perfect' : 'sqrt_imperfect';
        return self::build($key, [
            'aFmt'         => $aFmt,
            'vFmt'         => $vFmt,
            'precision'    => (string)$precision,
            'vRoundedFmt'  => $vRoundedFmt,
        ]);
    }

    public static function radicalOperation(
        string $nFmt,
        string $aFmt,
        string $vFmt,
        string $suffix
    ): StepText {
        return self::build('radicalOperation', [
            'nFmt'   => $nFmt,
            'aFmt'   => $aFmt,
            'vFmt'   => $vFmt,
            'suffix' => $suffix,
        ]);
    }

    public static function symbolicOperation(
        string $opName,
        string $opNameFa,
        string $lvStr,
        string $rvStr,
        string $combined,
        string $opSym
    ): StepText {
        return self::build('symbolicOperation', [
            'opName'    => $opName,
            'opNameFa'  => $opNameFa,
            'lvStr'     => $lvStr,
            'rvStr'     => $rvStr,
            'combined'  => $combined,
            'opSym'     => $opSym,
        ]);
    }

    public static function addition(string $lFmt, string $rFmt, string $vFmt, bool $rIsNeg): StepText
    {
        $note = $rIsNeg ? ' (adding a negative is equivalent to subtracting ' . ltrim($rFmt, '-') . ')' : '';
        return self::build('addition', [
            'l'    => $lFmt,
            'r'    => $rFmt,
            'v'    => $vFmt,
            'note' => $note,
        ]);
    }

    public static function subtraction(string $lFmt, string $rFmt, string $vFmt, bool $rIsNeg): StepText
    {
        $note = $rIsNeg ? ' (subtracting a negative is equivalent to adding ' . ltrim($rFmt, '-') . ')' : '';
        return self::build('subtraction', [
            'l'    => $lFmt,
            'r'    => $rFmt,
            'v'    => $vFmt,
            'note' => $note,
        ]);
    }

    public static function multiplicationOverflow(string $lFmt, string $rFmt): StepText
    {
        return self::build('multiplicationOverflow', [
            'l' => $lFmt,
            'r' => $rFmt,
        ]);
    }

    public static function multiplication(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        bool $implicit,
        string $fracNote
    ): StepText {
        $implNote = $implicit
            ? ' (implicit multiplication — two adjacent terms without an explicit × symbol)'
            : '';
        return self::build('multiplication', [
            'l'        => $lFmt,
            'r'        => $rFmt,
            'v'        => $vFmt,
            'implNote' => $implNote,
            'fracNote' => $fracNote,
        ]);
    }

    public static function division(string $lFmt, string $rFmt, string $vFmt, string $fracNote): StepText
    {
        return self::build('division', [
            'l'        => $lFmt,
            'r'        => $rFmt,
            'v'        => $vFmt,
            'fracNote' => $fracNote,
        ]);
    }

    public static function powPreOverflow(string $lFmt, string $rFmt, int $approxExp): StepText
    {
        return self::build('powPreOverflow', [
            'l'         => $lFmt,
            'r'         => $rFmt,
            'approxExp' => (string)$approxExp,
        ]);
    }

    public static function powPostOverflow(string $lFmt, string $rFmt): StepText
    {
        return self::build('powPostOverflow', [
            'l' => $lFmt,
            'r' => $rFmt,
        ]);
    }

    public static function exponentiation(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        string $typeEn,
        string $typeFa
    ): StepText {
        return self::build('exponentiation', [
            'l'      => $lFmt,
            'r'      => $rFmt,
            'v'      => $vFmt,
            'typeEn' => $typeEn,
            'typeFa' => $typeFa,
        ]);
    }

    public static function powTypeDescription(
        string $lFmt,
        string $rFmt,
        string $vFmt,
        float $r
    ): array {
        if (abs($r) < 1e-9) {
            return ['en' => self::build('pow_zero', [])->getEn(), 'fa' => self::build('pow_zero', [])->getFa()];
        }
        if (abs($r - 1.0) < 1e-9) {
            return ['en' => self::build('pow_one', [])->getEn(), 'fa' => self::build('pow_one', [])->getFa()];
        }
        if (abs($r - 2.0) < 1e-9) {
            return [
                'en' => self::build('pow_square', ['l' => $lFmt, 'v' => $vFmt])->getEn(),
                'fa' => self::build('pow_square', ['l' => $lFmt, 'v' => $vFmt])->getFa(),
            ];
        }
        if (abs($r - 3.0) < 1e-9) {
            return [
                'en' => self::build('pow_cube', ['l' => $lFmt, 'v' => $vFmt])->getEn(),
                'fa' => self::build('pow_cube', ['l' => $lFmt, 'v' => $vFmt])->getFa(),
            ];
        }
        if ($r < 0.0) {
            return [
                'en' => self::build('pow_negative', ['v' => $vFmt])->getEn(),
                'fa' => self::build('pow_negative', ['v' => $vFmt])->getFa(),
            ];
        }
        $count = (int)abs($r);
        return [
            'en' => self::build('pow_repeated', ['l' => $lFmt, 'count' => (string)$count])->getEn(),
            'fa' => self::build('pow_repeated', ['l' => $lFmt, 'count' => (string)$count])->getFa(),
        ];
    }

    public static function solverStart(string $eq, string $unk): StepText
    {
        return self::build('solverStart', ['eq' => $eq, 'unk' => $unk]);
    }

    public static function solverSimplify(
        string $unk,
        string $lhsConstFmt,
        string $lhsCoeffFmt,
        string $rhsConstFmt,
        string $rhsCoeffFmt,
        string $equationFmt
    ): StepText {
        return self::build('solverSimplify', [
            'unk'        => $unk,
            'lhsConst'   => $lhsConstFmt,
            'lhsCoeff'   => $lhsCoeffFmt,
            'rhsConst'   => $rhsConstFmt,
            'rhsCoeff'   => $rhsCoeffFmt,
            'equation'   => $equationFmt,
        ]);
    }

    public static function solverCollect(
        string $unk,
        string $lhsCoeffFmt,
        string $rhsCoeffFmt,
        string $netCoeffFmt,
        string $lhsConstFmt,
        string $rhsConstFmt,
        string $netRhsFmt,
        string $resultFmt
    ): StepText {
        return self::build('solverCollect', [
            'unk'       => $unk,
            'lhsCoeff'  => $lhsCoeffFmt,
            'rhsCoeff'  => $rhsCoeffFmt,
            'netCoeff'  => $netCoeffFmt,
            'lhsConst'  => $lhsConstFmt,
            'rhsConst'  => $rhsConstFmt,
            'netRhs'    => $netRhsFmt,
            'result'    => $resultFmt,
        ]);
    }

    public static function solverDegenerate(string $unk, bool $isIdentity, string $constFmt): StepText
    {
        $key = $isIdentity ? 'solverDegenerate_identity' : 'solverDegenerate_contradiction';
        return self::build($key, [
            'unk'      => $unk,
            'constFmt' => $constFmt,
        ]);
    }

    public static function solverDivideIsolated(string $unk, string $solFmt): StepText
    {
        return self::build('solverDivideIsolated', [
            'unk'    => $unk,
            'solFmt' => $solFmt,
        ]);
    }

    public static function solverDivide(
        string $unk,
        string $netCoeffFmt,
        string $netRhsFmt,
        string $solFmt
    ): StepText {
        return self::build('solverDivide', [
            'unk'      => $unk,
            'netCoeff' => $netCoeffFmt,
            'netRhs'   => $netRhsFmt,
            'solFmt'   => $solFmt,
        ]);
    }

    public static function solverNonLinear(string $unk, string $solFmt, string $devDetail): StepText
    {
        return self::build('solverNonLinear', [
            'unk'       => $unk,
            'solFmt'    => $solFmt,
            'devDetail' => $devDetail,
        ]);
    }

    public static function solverVerify(
        string $unk,
        string $solFmt,
        string $eq,
        string $lhsFmt,
        string $rhsFmt,
        string $diffFmt,
        bool $ok
    ): StepText {
        $key = $ok ? 'solverVerify_ok' : 'solverVerify_approx';
        return self::build($key, [
            'unk'     => $unk,
            'solFmt'  => $solFmt,
            'eq'      => $eq,
            'lhsFmt'  => $lhsFmt,
            'rhsFmt'  => $rhsFmt,
            'diffFmt' => $diffFmt,
        ]);
    }

    public static function expressionStart(string $expression): StepText
    {
        return self::build('expressionStart', ['expression' => $expression]);
    }

    public static function equationStart(string $equation): StepText
    {
        return self::build('equationStart', ['equation' => $equation]);
    }

    public static function classificationExpression(): StepText
    {
        return self::build('classificationExpression', []);
    }

    public static function classificationEquation(string $unknown): StepText
    {
        return self::build('classificationEquation', ['unknown' => $unknown]);
    }

    public static function algebraicRuleApplied(string $ruleName, string $before, string $after): StepText
    {
        return self::build('algebraicRuleApplied', [
            'ruleName' => $ruleName,
            'before'   => $before,
            'after'    => $after,
        ]);
    }

    public static function solverExtractLinear(string $unknown, string $aValue, string $bValue): StepText
    {
        return self::build('solverExtractLinear', [
            'unknown' => $unknown,
            'aValue'  => $aValue,
            'bValue'  => $bValue,
        ]);
    }

    public static function errorDivisionByZero(): StepText
    {
        return self::build('errorDivisionByZero', []);
    }

    public static function errorImaginarySqrt(string $radicand): StepText
    {
        return self::build('errorImaginarySqrt', ['radicand' => $radicand]);
    }

    public static function errorNonIntegerExponent(string $exponent): StepText
    {
        return self::build('errorNonIntegerExponent', ['exponent' => $exponent]);
    }

    public static function finalExpressionResult(string $result): StepText
    {
        return self::build('finalExpressionResult', ['result' => $result]);
    }

    public static function finalEquationResult(string $unknown, string $result): StepText
    {
        return self::build('finalEquationResult', [
            'unknown' => $unknown,
            'result'  => $result,
        ]);
    }

    public static function finalSimplified(string $result): StepText
    {
        return self::build('finalSimplified', ['result' => $result]);
    }
}