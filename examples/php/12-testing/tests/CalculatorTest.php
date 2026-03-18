<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Calculator.php';

/**
 * Тема 12 – Тестване: Unit тест за Calculator
 *
 * Стартиране:
 *   composer install
 *   vendor/bin/phpunit tests/
 *
 * Основни PHPUnit концепции:
 *   - Test class наследява TestCase
 *   - Всеки test метод е публичен и започва с "test" (или има @test анотация)
 *   - assert* методи проверяват очакваните резултати
 *   - setUp() се изпълнява преди всеки тест
 *   - @dataProvider захранва тест с множество входни данни
 */
class CalculatorTest extends TestCase
{
    private Calculator $calc;

    // ── setUp се изпълнява преди ВСЕКИ тест ──────────────────────────
    protected function setUp(): void
    {
        $this->calc = new Calculator();
    }

    // ── Основни тестове ──────────────────────────────────────────────

    public function testAdd(): void
    {
        $this->assertSame(5.0, $this->calc->add(2, 3));
        $this->assertSame(0.0, $this->calc->add(-1, 1));
    }

    public function testSubtract(): void
    {
        $this->assertSame(1.0, $this->calc->subtract(3, 2));
        $this->assertSame(-5.0, $this->calc->subtract(0, 5));
    }

    public function testMultiply(): void
    {
        $this->assertSame(12.0, $this->calc->multiply(3, 4));
        $this->assertSame(0.0, $this->calc->multiply(5, 0));
        $this->assertSame(-6.0, $this->calc->multiply(-2, 3));
    }

    public function testDivide(): void
    {
        $this->assertSame(2.5, $this->calc->divide(5, 2));
        $this->assertSame(-3.0, $this->calc->divide(-6, 2));
    }

    // ── Тест за изключение ───────────────────────────────────────────

    public function testDivideByZeroThrowsException(): void
    {
        // Очакваме InvalidArgumentException при деление на нула
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');

        $this->calc->divide(10, 0);
    }

    // ── Data Provider ────────────────────────────────────────────────
    // Тества се с множество входни стойности от един тест

    /**
     * @dataProvider percentageProvider
     */
    public function testPercentage(float $value, float $pct, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->calc->percentage($value, $pct), 0.001);
    }

    /** @return array<string, array{float, float, float}> */
    public static function percentageProvider(): array
    {
        return [
            '10% от 200'  => [200.0, 10.0, 20.0],
            '50% от 100'  => [100.0, 50.0, 50.0],
            '0% от 500'   => [500.0,  0.0,  0.0],
            '100% от 75'  => [ 75.0,100.0, 75.0],
            '7.5% от 400' => [400.0,  7.5, 30.0],
        ];
    }

    // ── Групиране (annotations) ─────────────────────────────────────

    /**
     * @group arithmetic
     */
    public function testAddIsCommutative(): void
    {
        $a = 7.3;
        $b = 2.1;
        $this->assertSame($this->calc->add($a, $b), $this->calc->add($b, $a));
    }
}
