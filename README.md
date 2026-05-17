# CAS – A Lightweight Computer Algebra System for PHP

<div align="center">

**A lightweight, zero-dependency Computer Algebra System (CAS) library for PHP with step-by-step human-readable explanations.**

[![Latest Version](https://img.shields.io/packagist/v/sobhanmohammadi/cas)](https://packagist.org/packages/sobhanmohammadi/cas)
[![Required PHP Version](https://img.shields.io/packagist/php-v/sobhanmohammadi/cas)](https://packagist.org/packages/sobhanmohammadi/cas)
[![License](https://img.shields.io/packagist/l/sobhanmohammadi/cas)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/sobhanmohammadi/cas)](https://packagist.org/packages/sobhanmohammadi/cas)

</div>

---

## 📖 About

**CAS** is a Computer Algebra System written entirely in PHP. Unlike numerical calculators that work with floating-point approximations, this library manipulates mathematical expressions **symbolically** — just like a human would when solving algebra problems by hand.

The killer feature? **It explains every single step in plain, human-readable text** (Persian & English). Whether you're building an educational platform, a math tutor bot, or simply need exact symbolic computation in your PHP application, this library has you covered — with absolutely **zero external dependencies**.

---

## ✨ Key Features

| Category | Details |
|----------|---------|
| **🔤 Symbolic Computation** | Exact arithmetic on integers, rationals, and complex numbers — no floating-point errors |
| **📊 Algebraic Simplification** | Fully recursive simplification engine with convergence detection |
| **🧩 Equation Solving** | Symbolic & numeric solvers (currently linear; higher-order planned) |
| **📝 Step-by-Step Explanations** | Every operation is recorded and explained in Persian & English |
| **🌳 AST-Based Architecture** | All expressions are parsed into a tree structure for easy manipulation |
| **🔢 Rich Numeric Support** | Integers (GMP), Rationals, Complex numbers, π |
| **🪶 Zero Dependencies** | Pure PHP — only needs GMP extension (bundled with PHP by default) |
| **📐 PSR-4 Compliant** | Clean, modern namespace structure |
| **⚡ Lightweight** | Minimal overhead; designed for embedding in larger projects |

---

## 🚀 Installation

Install via Composer:

```bash
composer require sobhanmohammadi/cas