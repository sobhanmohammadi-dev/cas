<?php
namespace Sobhanmohammadi\CAS\StepExplainer;

final class Texts
{
    /** @var array<string, array<string, string>> */
    public static array $messages = [
        'piSubstitution' => [
            'en'          => "Substituting the numerical value of π (pi). π is an irrational constant — the ratio of a circle's circumference to its diameter. Substituted value: π = {decFmt}.",
            'fa'          => "جایگذاری مقدار عددی π (پی). π یک ثابت گنگ ریاضی است: نسبت محیط دایره به قطر آن. مقدار عددی: π = {decFmt}.",
            'formula'     => "pi = {decFmt}",
            'calculation' => "pi → {decFmt}",
        ],
        'variableSubstitution' => [
            'en'          => "Substituting variable '{varName}' with its given value {valFmt}. Every occurrence of '{varName}' in the expression is replaced by {valFmt}.",
            'fa'          => "جایگذاری متغیر '{varName}' با مقدار داده‌شده {valFmt}.",
            'formula'     => "{varName} = {valFmt}",
            'calculation' => "{varName} → {valFmt}",
        ],
        'constantChain' => [
            'en'          => "Evaluating constant expression {exprStr} = {resFmt}.",
            'fa'          => "محاسبه عبارت ثابت {exprStr} = {resFmt}.",
            'formula'     => "{exprStr}",
            'calculation' => "{exprStr} = {resFmt}",
        ],
        'unaryNegation' => [
            'en'          => "Negating {origFmt}: multiplying by −1 flips the sign. −({origFmt}) = {resFmt}.",
            'fa'          => "منفی کردن: −({origFmt}) = {resFmt}.",
            'formula'     => "-({origFmt})",
            'calculation' => "-({origFmt}) = {resFmt}",
        ],
        'trigOperation' => [
            'en'          => "Computing {fnName}({argFmt}) (argument in radians). Result, rounded to {precision} decimal places: {vFmt}.",
            'fa'          => "محاسبه {fnName}({argFmt}) (بر حسب رادیان). نتیجه (تا {precision} رقم اعشار): {vFmt}.",
            'formula'     => "{fnName}({argFmt})",
            'calculation' => "{fnName}({argFmt}) = {vFmt}",
        ],
        'atan2Operation' => [
            'en'          => "Computing atan2({yFmt}, {xFmt}) (two-argument inverse tangent, resolves the correct quadrant). Result, rounded to {precision} decimal places: {vFmt}.",
            'fa'          => "محاسبه atan2({yFmt}, {xFmt}) (آرک‌تانژانت دوآرگومانی، ربع صحیح را تعیین می‌کند). نتیجه (تا {precision} رقم اعشار): {vFmt}.",
            'formula'     => "atan2({yFmt}, {xFmt})",
            'calculation' => "atan2({yFmt}, {xFmt}) = {vFmt}",
        ],
        'sqrt_perfect' => [
            'en'          => "Computing √{aFmt}: {aFmt} is a perfect square ({vFmt} × {vFmt} = {aFmt}), so the root is the whole number {vFmt}.",
            'fa'          => "محاسبه √{aFmt}: {aFmt} یک مربع کامل است ({vFmt} × {vFmt} = {aFmt}). نتیجه: {vFmt}.",
            'formula'     => "sqrt({aFmt})",
            'calculation' => "sqrt({aFmt}) = {vFmt}",
        ],
        'sqrt_imperfect' => [
            'en'          => "Computing √{aFmt}: {aFmt} is not a perfect square, so the result is irrational, rounded to {precision} decimal places. Result: {vFmt}.",
            'fa'          => "محاسبه √{aFmt}: {aFmt} مربع کامل نیست. نتیجه تقریبی (تا {precision} رقم اعشار): {vFmt}.",
            'formula'     => "sqrt({aFmt})",
            'calculation' => "sqrt({aFmt}) = {vFmt}",
        ],
        'radicalOperation' => [
            'en'          => "Computing the {nFmt}-th root ({suffix}) of {aFmt}. ⁿ√a = a^(1/n). Result: {vFmt}.",
            'fa'          => "محاسبه ریشه {nFmt}-ام ({suffix}) مقدار {aFmt}. نتیجه: {vFmt}.",
            'formula'     => "radical({nFmt}, {aFmt}) = ⁿ√{aFmt}",
            'calculation' => "radical({nFmt}, {aFmt}) = {vFmt}",
        ],
        'symbolicOperation' => [
            'en'          => "Combining terms symbolically ({opName}). Left: {lvStr}, Right: {rvStr}. The result is kept symbolic: {combined}.",
            'fa'          => "ترکیب نمادین ({opNameFa}). چپ: {lvStr}، راست: {rvStr}. نتیجه نمادین: {combined}.",
            'formula'     => "{lvStr} {opSym} {rvStr}",
            'calculation' => "{combined}",
        ],
        'addition' => [
            'en'          => "Adding {l} + {r}{note}. Result: {v}.",
            'fa'          => "جمع: {l} + {r} = {v}.",
            'formula'     => "{l} + {r}",
            'calculation' => "{l} + {r} = {v}",
        ],
        'subtraction' => [
            'en'          => "Subtracting {r} from {l}{note}. Result: {v}.",
            'fa'          => "تفریق: {l} - {r} = {v}.",
            'formula'     => "{l} - {r}",
            'calculation' => "{l} - {r} = {v}",
        ],
        'multiplication' => [
            'en'          => "Multiplying {l} × {r}{implNote}.{fracNote} Result: {v}.",
            'fa'          => "ضرب: {l} × {r} = {v}.",
            'formula'     => "{l} * {r}",
            'calculation' => "{l} * {r} = {v}",
        ],
        'division' => [
            'en'          => "Dividing {l} ÷ {r}.{fracNote} Result: {v}.",
            'fa'          => "تقسیم: {l} ÷ {r} = {v}.",
            'formula'     => "({l}) / ({r})",
            'calculation' => "{l} / {r} = {v}",
        ],
        'exponentiation' => [
            'en'          => "Computing {l}^{r}. {typeEn} Result: {v}.",
            'fa'          => "محاسبه {l} به توان {r}. {typeFa} نتیجه: {v}.",
            'formula'     => "{l}^{r}",
            'calculation' => "{l}^{r} = {v}",
        ],
        'pow_zero' => [
            'en' => "Any non-zero number to the power 0 equals 1 (a^0 = 1).",
            'fa' => "هر عدد غیرصفر به توان 0 برابر 1 است.",
        ],
        'pow_one' => [
            'en' => "Any number to the power 1 equals itself (a^1 = a).",
            'fa' => "هر عدد به توان 1 برابر خودش است.",
        ],
        'pow_square' => [
            'en' => "Squaring: {l} × {l} = {v}.",
            'fa' => "مربع کردن: {l} × {l} = {v}.",
        ],
        'pow_cube' => [
            'en' => "Cubing: {l}³ = {v}.",
            'fa' => "مکعب کردن: {l}³ = {v}.",
        ],
        'pow_negative' => [
            'en' => "Negative exponent: a^(−b) = 1/(a^b) = {v}.",
            'fa' => "توان منفی: 1/(a^b) = {v}.",
        ],
        'pow_repeated' => [
            'en' => "Repeated multiplication: {l} multiplied by itself {count} times.",
            'fa' => "ضرب مکرر: {count} بار ضرب شده.",
        ],
        'solverSimplify' => [
            'en'          => "Simplify each side. Left constant: {lhsConst}, left coefficient of {unk}: {lhsCoeff}. Right constant: {rhsConst}, right coefficient of {unk}: {rhsCoeff}. Equation: {equation}.",
            'fa'          => "مقادیر ثابت را ساده می‌کنیم. طرف چپ ثابت: {lhsConst}، ضریب {unk}: {lhsCoeff}. طرف راست ثابت: {rhsConst}، ضریب {unk}: {rhsCoeff}. معادله: {equation}.",
            'formula'     => "{equation}",
            'calculation' => "{equation}",
        ],
        'solverCollect' => [
            'en'          => "Collect '{unk}' terms on the left and constants on the right. Net coefficient: {lhsCoeff} − {rhsCoeff} = {netCoeff}. Right side: {rhsConst} − {lhsConst} = {netRhs}. Result: {result}.",
            'fa'          => "همه جملات '{unk}' را به طرف چپ منتقل می‌کنیم. ضریب خالص: {lhsCoeff} − {rhsCoeff} = {netCoeff}. طرف راست: {rhsConst} − {lhsConst} = {netRhs}. نتیجه: {result}.",
            'formula'     => "{result}",
            'calculation' => "{result}",
        ],
        'solverDegenerate_identity' => [
            'en'          => "The coefficient of '{unk}' cancels to 0. The equation reduces to 0 = 0, which is always true: infinitely many solutions.",
            'fa'          => "ضریب '{unk}' صفر می‌شود. معادله به 0 = 0 تبدیل می‌شود که همیشه درست است: بی‌نهایت جواب.",
            'formula'     => "0·{unk} = 0",
            'calculation' => "0 = 0 → ∞ solutions",
        ],
        'solverDegenerate_contradiction' => [
            'en'          => "The coefficient of '{unk}' cancels to 0, but the constant is {constFmt} ≠ 0. The equation reduces to {constFmt} = 0, which is never true: no solution.",
            'fa'          => "ضریب '{unk}' صفر است، ولی ثابت {constFmt} ≠ 0. معادله {constFmt} = 0 هرگز درست نیست: جوابی وجود ندارد.",
            'formula'     => "0·{unk} = {constFmt}",
            'calculation' => "{constFmt} = 0 → no solution",
        ],
        'solverDivideIsolated' => [
            'en'          => "The coefficient of '{unk}' is already 1, so '{unk}' is directly isolated. Solution: {unk} = {solFmt}.",
            'fa'          => "ضریب '{unk}' از قبل 1 است، بنابراین جواب مستقیم است: {unk} = {solFmt}.",
            'formula'     => "{unk} = {solFmt}",
            'calculation' => "{unk} = {solFmt}",
        ],
        'solverDivide' => [
            'en'          => "Divide both sides by {netCoeff} to isolate '{unk}': {netRhs} ÷ {netCoeff} = {solFmt}. Solution: {unk} = {solFmt}.",
            'fa'          => "هر دو طرف را بر {netCoeff} تقسیم می‌کنیم: {netRhs} ÷ {netCoeff} = {solFmt}. جواب: {unk} = {solFmt}.",
            'formula'     => "{unk} = {netRhs} ÷ {netCoeff}",
            'calculation' => "{unk} = {solFmt}",
        ],
        'solverNonLinear' => [
            'en'          => "⚠ Non-linear equation detected at: {devDetail}. This solver only handles linear equations.",
            'fa'          => "⚠ معادله غیرخطی تشخیص داده شد: {devDetail}. این حل‌کننده فقط معادلات خطی را پشتیبانی می‌کند.",
            'formula'     => "Non-linear equation",
            'calculation' => "",
        ],
        'solverVerify_ok' => [
            'en'          => "Verify: substitute {unk} = {solFmt} into {eq}. LHS = {lhsFmt}, RHS = {rhsFmt}. Both sides equal ✓ — the solution {unk} = {solFmt} is correct.",
            'fa'          => "تأیید: {unk} = {solFmt} را جایگذاری می‌کنیم. طرف چپ: {lhsFmt}، طرف راست: {rhsFmt}. هر دو برابرند ✓ — جواب {unk} = {solFmt} صحیح است.",
            'formula'     => "Substitute {unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt}",
            'calculation' => "{unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt} ✓",
        ],
        'solverVerify_approx' => [
            'en'          => "Verify: substitute {unk} = {solFmt} into {eq}. LHS = {lhsFmt}, RHS = {rhsFmt}. Difference = {diffFmt} ⚠ — approximate solution.",
            'fa'          => "تأیید: {unk} = {solFmt} را جایگذاری می‌کنیم. طرف چپ: {lhsFmt}، طرف راست: {rhsFmt}. تفاوت = {diffFmt} ⚠.",
            'formula'     => "Substitute {unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt}",
            'calculation' => "{unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt} ⚠",
        ],
        'expressionStart' => [
            'en'          => "Received expression: {expression}",
            'fa'          => "عبارت دریافت‌شده: {expression}",
            'formula'     => "{expression}",
            'calculation' => "{expression}",
        ],
        'equationStart' => [
            'en'          => "Received equation: {equation}",
            'fa'          => "معادله دریافت‌شده: {equation}",
            'formula'     => "{equation}",
            'calculation' => "{equation}",
        ],
        'classificationEquation' => [
            'en'          => "This is an equation with unknown '{unknown}'. Goal: find the value(s) of '{unknown}' that satisfy the equation.",
            'fa'          => "این یک معادله با مجهول '{unknown}' است. هدف یافتن مقدار(های) '{unknown}' است.",
            'formula'     => "",
            'calculation' => "",
        ],
        'algebraicRuleApplied' => [
            'en'          => "Applying rule '{ruleName}': {before} → {after}",
            'fa'          => "اعمال قاعده '{ruleName}': {before} → {after}",
            'formula'     => "{before} → {after}",
            'calculation' => "{before} = {after}",
        ],
        'errorDivisionByZero' => [
            'en'          => "Division by zero is undefined. The expression cannot be evaluated.",
            'fa'          => "تقسیم بر صفر تعریف‌نشده است.",
            'formula'     => "",
            'calculation' => "",
        ],
        'errorImaginarySqrt' => [
            'en'          => "Square root of a negative number ({radicand}) is not defined in the real number system.",
            'fa'          => "جذر یک عدد منفی ({radicand}) در اعداد حقیقی تعریف‌نشده است.",
            'formula'     => "√({radicand})",
            'calculation' => "",
        ],
        'finalExpressionResult' => [
            'en'          => "Simplified expression: {result}",
            'fa'          => "عبارت ساده‌شده: {result}",
            'formula'     => "{result}",
            'calculation' => "{result}",
        ],
        'finalEquationResult' => [
            'en'          => "Solution: {unknown} = {result}",
            'fa'          => "جواب: {unknown} = {result}",
            'formula'     => "{unknown} = {result}",
            'calculation' => "{unknown} = {result}",
        ],
        'finalSimplified' => [
            'en'          => "The expression cannot be simplified further. Final result: {result}.",
            'fa'          => "عبارت بیش از این ساده نمی‌شود. نتیجهٔ نهایی: {result}.",
            'formula'     => "{result}",
            'calculation' => "{result}",
        ],
    ];
}
