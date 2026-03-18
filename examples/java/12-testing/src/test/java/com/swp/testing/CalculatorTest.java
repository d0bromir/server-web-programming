package com.swp.testing;

import org.junit.jupiter.api.*;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.*;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Unit тест без Spring контекст.
 * Тества Calculator директно – бърз, изолиран.
 */
class CalculatorTest {

    private Calculator calc;

    @BeforeEach
    void setUp() {
        calc = new Calculator();
    }

    @Test
    void testAdd() {
        assertEquals(5.0, calc.add(2, 3));
    }

    @Test
    void testSubtract() {
        assertEquals(1.0, calc.subtract(3, 2));
    }

    @Test
    void testMultiply() {
        assertEquals(6.0, calc.multiply(2, 3));
    }

    @Test
    void testDivide() {
        assertEquals(2.5, calc.divide(5, 2));
    }

    @Test
    void testDivideByZeroThrowsException() {
        IllegalArgumentException ex = assertThrows(
            IllegalArgumentException.class,
            () -> calc.divide(10, 0)
        );
        assertTrue(ex.getMessage().contains("нула"));
    }

    /** Параметризиран тест – изпълнява се 3 пъти */
    @ParameterizedTest
    @CsvSource({"1,2,3", "0,0,0", "-1,1,0"})
    void testAddParameterized(double a, double b, double expected) {
        assertEquals(expected, calc.add(a, b));
    }

    @Test
    void testAddIsCommutative() {
        assertEquals(calc.add(3, 7), calc.add(7, 3));
    }
}
