<?php
declare(strict_types=1);

/**
 * Клас Calculator – използван за демонстрация на unit тестване.
 */
class Calculator
{
    public function add(float $a, float $b): float
    {
        return $a + $b;
    }

    public function subtract(float $a, float $b): float
    {
        return $a - $b;
    }

    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    public function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new InvalidArgumentException('Division by zero');
        }
        return $a / $b;
    }

    public function percentage(float $value, float $percent): float
    {
        return $value * $percent / 100;
    }
}
