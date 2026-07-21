<?php
namespace Sobhanmohammadi\CAS\Services;

use Sobhanmohammadi\CAS\Nodes\MathNode;

/**
 * One root of a quadratic equation.
 *
 * For a real root, `getReal()` IS the root and `getImaginary()` is null.
 * For a non-real (complex-conjugate-pair) root, the value is
 * `getReal() + getImaginary() * i` — both parts are returned as exact or
 * symbolic MathNode expressions (e.g. a RationalNode, or a
 * DivideNode/SqrtNode expression when the discriminant is not a perfect
 * square), never pre-collapsed to a decimal approximation, matching the
 * exact-first philosophy of the rest of the symbolic layer.
 */
class QuadraticRoot
{
    private MathNode $real;
    private ?MathNode $imaginary;

    public function __construct(MathNode $real, ?MathNode $imaginary = null)
    {
        $this->real      = $real;
        $this->imaginary = $imaginary;
    }

    public function isReal(): bool { return $this->imaginary === null; }

    public function getReal(): MathNode { return $this->real; }

    /** Coefficient of i, or null for a real root. */
    public function getImaginary(): ?MathNode { return $this->imaginary; }

    public function __toString(): string
    {
        if ($this->isReal()) {
            return (string) $this->real;
        }
        return (string) $this->real . ' + (' . (string) $this->imaginary . ')i';
    }
}
