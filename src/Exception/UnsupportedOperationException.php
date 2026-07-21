<?php
namespace Sobhanmohammadi\CAS\Exception;

/**
 * The requested operation is well-formed but not supported by the layer
 * that was asked to perform it — e.g. an exact/GMP evaluation of a
 * transcendental function (sin/cos/tan/asin/atan are never exactly
 * representable and must go through the decimal StepExplainer layer
 * instead), or a solver being asked to solve an equation whose degree it
 * does not implement (e.g. cubic+ given to the quadratic solver).
 */
class UnsupportedOperationException extends CasException
{
}
