<?php
namespace CAS\StepExplainer;

final class Texts
{
    public static array $messages = [
        'piSubstitution' => [
            'en' => "Substituting the numerical value of π (pi). π is an irrational constant — the ratio of a circle's circumference to its diameter. Substituted value: π = {decFmt}.",
            'fa' => "جایگذاری مقدار عددی π (پی). π یک ثابت گنگ ریاضی است: نسبت محیط دایره به قطر آن. مقدار عددی: π = {decFmt}.",
            'formula' => "pi = {decFmt}",
            'calculation' => "pi → {decFmt}",
        ],
        'variableSubstitution' => [
            'en' => "Substituting variable '{varName}' with its given value {valFmt}. Every occurrence of '{varName}' in the expression is replaced by {valFmt}.",
            'fa' => "جایگذاری متغیر '{varName}' با مقدار داده‌شده {valFmt}.",
            'formula' => "{varName} = {valFmt}",
            'calculation' => "{varName} → {valFmt}",
        ],
        'mergedLeafOperation' => [
            'en' => "Substituting {substitutions}: {originalExpr} → {instantiated} = {resFmt}.",
            'fa' => "جایگذاری {substitutions}: {originalExpr} → {instantiated} = {resFmt}.",
            'formula' => "{instantiated}",
            'calculation' => "{instantiated} = {resFmt}",
        ],
        'constantChain' => [
            'en' => "Evaluating constant expression {exprStr} = {resFmt}.",
            'fa' => "محاسبه عبارت ثابت {exprStr} = {resFmt}.",
            'formula' => "{exprStr}",
            'calculation' => "{exprStr} = {resFmt}",
        ],
        'unaryNegation' => [
            'en' => "Negating {origFmt}: multiplying by −1 flips the sign. −({origFmt}) = {resFmt}.",
            'fa' => "منفی کردن: −({origFmt}) = {resFmt}.",
            'formula' => "-({origFmt})",
            'calculation' => "-({origFmt}) = {resFmt}",
        ],
        'sqrt_perfect' => [
            'en' => "Computing √{aFmt}: the square root is the number that, when multiplied by itself, gives {aFmt}. {aFmt} is a perfect square ({vRoundedFmt} × {vRoundedFmt} = {aFmt}), so the root is a whole number. Result: {vFmt}.",
            'fa' => "محاسبه √{aFmt}: جذر عددی است که وقتی در خودش ضرب شود برابر {aFmt} می‌شود. نتیجه: {vFmt}.",
            'formula' => "sqrt({aFmt})",
            'calculation' => "sqrt({aFmt}) = {vFmt}",
        ],
        'sqrt_imperfect' => [
            'en' => "Computing √{aFmt}: the square root is the number that, when multiplied by itself, gives {aFmt}. Since {aFmt} is not a perfect square, the result is irrational, rounded to {precision} decimal places. Result: {vFmt}.",
            'fa' => "محاسبه √{aFmt}: جذر عددی است که وقتی در خودش ضرب شود برابر {aFmt} می‌شود. نتیجه: {vFmt}.",
            'formula' => "sqrt({aFmt})",
            'calculation' => "sqrt({aFmt}) = {vFmt}",
        ],
        'radicalOperation' => [
            'en' => "Computing the {nFmt}-th root ({suffix}) of {aFmt}. The nth root is defined as ⁿ√a = a^(1/n). Calculation: ({aFmt})^(1/{nFmt}) = {vFmt}.",
            'fa' => "محاسبه ریشه {nFmt}-ام ({suffix}) مقدار {aFmt}. ⁿ√a = a^(1/n). نتیجه: {vFmt}.",
            'formula' => "radical({nFmt}, {aFmt}) = ⁿ√{aFmt}",
            'calculation' => "radical({nFmt}, {aFmt}) = {vFmt}",
        ],
        'symbolicOperation' => [
            'en' => "Combining terms symbolically ({opName}). Left: {lvStr}, Right: {rvStr}. Because the expression contains an unknown variable, the result is kept as a symbolic expression: {combined}.",
            'fa' => "ترکیب نمادین ({opNameFa}). چپ: {lvStr}، راست: {rvStr}. چون عبارت شامل متغیر مجهول است، نتیجه به صورت نمادین باقی می‌ماند: {combined}.",
            'formula' => "{lvStr} {opSym} {rvStr}",
            'calculation' => "{combined}",
        ],
        'addition' => [
            'en' => "Adding {l} + {r}{note}. Result: {v}.",
            'fa' => "جمع: {l} + {r} = {v}.",
            'formula' => "{l} + {r}",
            'calculation' => "{l} + {r} = {v}",
        ],
        'subtraction' => [
            'en' => "Subtracting {r} from {l}{note}. Result: {v}.",
            'fa' => "تفریق: {l} - {r} = {v}.",
            'formula' => "{l} - {r}",
            'calculation' => "{l} - {r} = {v}",
        ],
        'multiplication' => [
            'en' => "Multiplying {l} × {r}{implNote}.{fracNote} Result: {v}.",
            'fa' => "ضرب: {l} × {r} = {v}.",
            'formula' => "{l} * {r}",
            'calculation' => "{l} * {r} = {v}",
        ],
        'multiplicationOverflow' => [
            'en' => "Multiplying {l} × {r}. The result overflows the maximum representable number and is treated as infinity (∞).",
            'fa' => "ضرب {l} × {r}. نتیجه از بزرگ‌ترین عدد قابل نمایش بیشتر است و به بی‌نهایت (∞) تبدیل می‌شود.",
            'formula' => "{l} * {r}",
            'calculation' => "{l} * {r} → ∞",
        ],
        'division' => [
            'en' => "Dividing {l} ÷ {r}.{fracNote} Result: {v}.",
            'fa' => "تقسیم: {l} ÷ {r} = {v}.",
            'formula' => "({l}) / ({r})",
            'calculation' => "{l} / {r} = {v}",
        ],
        'exponentiation' => [
            'en' => "Computing {l}^{r}. {typeEn} Result: {v}.",
            'fa' => "محاسبه {l} به توان {r}. {typeFa} نتیجه: {v}.",
            'formula' => "{l}^{r}",
            'calculation' => "{l}^{r} = {v}",
        ],
        'powPreOverflow' => [
            'en' => "Computing {l}^{r}. The result is astronomically large (≈ 10^{approxExp}) and exceeds maximum float precision. Treated as ∞.",
            'fa' => "محاسبه {l} به توان {r}. نتیجه بسیار بزرگ است (≈ 10^{approxExp}). به عنوان ∞ در نظر گرفته می‌شود.",
            'formula' => "{l}^{r}",
            'calculation' => "{l}^{r} ≈ 10^{approxExp} → ∞",
        ],
        'powPostOverflow' => [
            'en' => "Computing {l}^{r}. The result exceeds the maximum storable number (overflow) and is treated as ∞.",
            'fa' => "محاسبه {l} به توان {r}. نتیجه از ظرفیت رایانه بیشتر است (سرریز) و ∞ در نظر گرفته می‌شود.",
            'formula' => "{l}^{r}",
            'calculation' => "{l}^{r} → ∞",
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
        'solverStart' => [
            'en' => "Start with the original equation: {eq}. Goal: isolate '{unk}' and find its exact numerical value.",
            'fa' => "با معادله اصلی شروع می‌کنیم: {eq}. هدف: جدا کردن '{unk}' و یافتن مقدار عددی آن.",
            'formula' => "{eq}",
            'calculation' => "{eq}",
        ],
        'solverSimplify' => [
            'en' => "Simplify the known (constant) values on each side. Left side constant: {lhsConst}, left coefficient of {unk}: {lhsCoeff}. Right side constant: {rhsConst}, right coefficient of {unk}: {rhsCoeff}. Equation: {equation}.",
            'fa' => "مقادیر ثابت را در هر طرف ساده می‌کنیم. طرف چپ ثابت: {lhsConst}، ضریب {unk}: {lhsCoeff}. طرف راست ثابت: {rhsConst}، ضریب {unk}: {rhsCoeff}. معادله: {equation}.",
            'formula' => "{equation}",
            'calculation' => "{equation}",
        ],
        'solverCollect' => [
            'en' => "Collect all terms containing '{unk}' on the LEFT and all constants on the RIGHT. Subtract {rhsCoeff}·{unk} from both sides → net coefficient: {lhsCoeff} − {rhsCoeff} = {netCoeff}. Subtract {lhsConst} from both sides → right side: {rhsConst} − {lhsConst} = {netRhs}. Result: {result}.",
            'fa' => "همه جملات شامل '{unk}' را به طرف چپ و همه ثابت‌ها را به طرف راست منتقل می‌کنیم. ضریب خالص {unk}: {lhsCoeff} − {rhsCoeff} = {netCoeff}. طرف راست: {rhsConst} − {lhsConst} = {netRhs}. نتیجه: {result}.",
            'formula' => "{result}",
            'calculation' => "{result}",
        ],
        'solverDegenerate_identity' => [
            'en' => "The coefficient of '{unk}' is 0 on both sides — '{unk}' cancels out entirely. The equation reduces to 0 = 0, which is ALWAYS true. Therefore 0·{unk} = 0 has INFINITELY MANY solutions: {unk} can be any real number.",
            'fa' => "ضریب '{unk}' صفر است — '{unk}' از هر دو طرف حذف می‌شود. معادله به 0 = 0 تبدیل می‌شود که همیشه درست است. بی‌نهایت جواب وجود دارد.",
            'formula' => "0·{unk} = 0",
            'calculation' => "0 = 0 → ∞ solutions",
        ],
        'solverDegenerate_contradiction' => [
            'en' => "The coefficient of '{unk}' is 0, but the constant term is {constFmt} ≠ 0. The equation reduces to {constFmt} = 0, which is NEVER true. Therefore 0·{unk} = {constFmt} has NO solution.",
            'fa' => "ضریب '{unk}' صفر است، ولی ثابت {constFmt} ≠ 0. معادله به {constFmt} = 0 تبدیل می‌شود که هرگز درست نیست. جوابی وجود ندارد.",
            'formula' => "0·{unk} = {constFmt}",
            'calculation' => "{constFmt} = 0 → no solution",
        ],
        'solverDivideIsolated' => [
            'en' => "The coefficient of '{unk}' is already 1, so '{unk}' is directly isolated. Therefore: {unk} = {solFmt}.",
            'fa' => "ضریب '{unk}' از قبل 1 است، بنابراین '{unk}' مستقیماً جدا شده است: {unk} = {solFmt}.",
            'formula' => "{unk} = {solFmt}",
            'calculation' => "{unk} = {solFmt}",
        ],
        'solverDivide' => [
            'en' => "Divide BOTH sides by the coefficient {netCoeff} to isolate '{unk}': {netCoeff}·{unk} ÷ {netCoeff} = {netRhs} ÷ {netCoeff}. Result: {unk} = {solFmt}.",
            'fa' => "هر دو طرف را بر ضریب {netCoeff} تقسیم می‌کنیم: {netRhs} ÷ {netCoeff} = {solFmt}. نتیجه: {unk} = {solFmt}.",
            'formula' => "{unk} = {netRhs} ÷ {netCoeff}",
            'calculation' => "{unk} = {solFmt}",
        ],
        'solverNonLinear' => [
            'en' => "⚠ Non-linear equation detected. The linear model fails at: {devDetail}. The value {unk} = {solFmt} is a LINEAR APPROXIMATION only — it may not be an exact root, and the equation may have additional roots. For a complete solution use Newton–Raphson, bisection, or a CAS.",
            'fa' => "⚠ معادله غیرخطی تشخیص داده شد. مدل خطی در نقاط زیر خطا دارد: {devDetail}. مقدار {unk} = {solFmt} فقط یک تقریب خطی است و ممکن است ریشه‌های دیگری نیز وجود داشته باشد.",
            'formula' => "Non-linear — linear approximation only",
            'calculation' => "{unk} ≈ {solFmt} (approximation)",
        ],
        'solverVerify_ok' => [
            'en' => "Verify by substituting {unk} = {solFmt} into {eq}. Left side = {lhsFmt}. Right side = {rhsFmt}. Both sides equal {lhsFmt} ✓ — the answer {unk} = {solFmt} is CORRECT.",
            'fa' => "جواب را با جایگذاری {unk} = {solFmt} در معادله تأیید می‌کنیم. طرف چپ: {lhsFmt}، طرف راست: {rhsFmt}. هر دو طرف برابر {lhsFmt} هستند ✓ — جواب {unk} = {solFmt} صحیح است.",
            'formula' => "Substitute {unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt}",
            'calculation' => "{unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt} ✓",
        ],
        'solverVerify_approx' => [
            'en' => "Verify by substituting {unk} = {solFmt} into {eq}. Left side = {lhsFmt}. Right side = {rhsFmt}. Difference = {diffFmt} ⚠ — the answer is a close approximation (due to non-linearity).",
            'fa' => "جواب را با جایگذاری {unk} = {solFmt} در معادله تأیید می‌کنیم. طرف چپ: {lhsFmt}، طرف راست: {rhsFmt}. تفاوت = {diffFmt} ⚠ — جواب تقریبی است.",
            'formula' => "Substitute {unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt}",
            'calculation' => "{unk} = {solFmt} → LHS = {lhsFmt}, RHS = {rhsFmt} ⚠",
        ],
        'expressionStart' => [
            'en' => "Received expression: {expression}",
            'fa' => "عبارت دریافت‌شده: {expression}",
            'formula' => "{expression}",
            'calculation' => "{expression}",
        ],
        'equationStart' => [
            'en' => "Received equation: {equation}",
            'fa' => "معادله دریافت‌شده: {equation}",
            'formula' => "{equation}",
            'calculation' => "{equation}",
        ],
        'classificationExpression' => [
            'en' => "This is an algebraic expression. The goal is to simplify it as much as possible using algebraic rules.",
            'fa' => "این یک عبارت جبری است. هدف ساده‌سازی آن تا حد ممکن با استفاده از قواعد جبری است.",
            'formula' => "",
            'calculation' => "",
        ],
        'classificationEquation' => [
            'en' => "This is an equation with unknown '{unknown}'. The goal is to find the value(s) of '{unknown}' that satisfy the equation.",
            'fa' => "این یک معادله با مجهول '{unknown}' است. هدف یافتن مقدار(های) '{unknown}' است که معادله را برقرار کند.",
            'formula' => "",
            'calculation' => "",
        ],
        'algebraicRuleApplied' => [
            'en' => "Applying rule '{ruleName}': {before} → {after}",
            'fa' => "اعمال قاعده '{ruleName}': {before} → {after}",
            'formula' => "{before} → {after}",
            'calculation' => "{before} = {after}",
        ],
        'solverExtractLinear' => [
            'en' => "Rewriting the expression in standard linear form a·{unknown} + b. Extracted: a = {aValue}, b = {bValue}.",
            'fa' => "بازنویسی عبارت به فرم خطی استاندارد a·{unknown} + b. استخراج‌شده: a = {aValue}، b = {bValue}.",
            'formula' => "{aValue}·{unknown} + {bValue}",
            'calculation' => "",
        ],
        'errorDivisionByZero' => [
            'en' => "Division by zero is undefined. The expression cannot be evaluated because the divisor is zero.",
            'fa' => "تقسیم بر صفر تعریف‌نشده است. به دلیل صفر بودن مقسوم‌علیه، محاسبه ممکن نیست.",
            'formula' => "",
            'calculation' => "",
        ],
        'errorImaginarySqrt' => [
            'en' => "Square root of a negative number is not defined in the real number system. The radicand {radicand} is negative.",
            'fa' => "جذر یک عدد منفی در مجموعه اعداد حقیقی تعریف‌نشده است. مقدار زیر رادیکال {radicand} منفی است.",
            'formula' => "√({radicand})",
            'calculation' => "",
        ],
        'errorNonIntegerExponent' => [
            'en' => "The exponent {exponent} is not an integer. Exact rational exponentiation is not supported.",
            'fa' => "توان {exponent} یک عدد صحیح نیست. توان‌های گویا به‌صورت دقیق پشتیبانی نمی‌شوند.",
            'formula' => "",
            'calculation' => "",
        ],
        'finalExpressionResult' => [
            'en' => "Simplified expression: {result}",
            'fa' => "عبارت ساده‌شده: {result}",
            'formula' => "{result}",
            'calculation' => "{result}",
        ],
        'finalEquationResult' => [
            'en' => "Solution: {unknown} = {result}",
            'fa' => "جواب: {unknown} = {result}",
            'formula' => "{unknown} = {result}",
            'calculation' => "{unknown} = {result}",
        ],
        'finalSimplified' => [
            'en'          => "The expression cannot be simplified further. Final simplified result: {result}.",
            'fa'          => "عبارت بیش از این ساده نمی‌شود. نتیجهٔ نهایی: {result}.",
            'formula'     => "{result}",
            'calculation' => "{result}",
        ],
    ];
}