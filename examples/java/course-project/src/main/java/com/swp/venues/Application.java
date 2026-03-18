package com.swp.venues;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

/**
 * Курсова задача – Любими заведения
 *
 * Изисквания:
 *   1. Публичен списък с разбивка по страници, търсене и филтриране
 *   2. Регистрация и вход (Spring Security + BCrypt)
 *   3. CRUD за заведения (само собственикът или ADMIN)
 *   4. REST API с Bearer-token автентикация
 *   5. Интеграция с Open Brewery DB (extern API)
 *   6. Маркиране на любими заведения (cookie)
 *   7. CSRF защита, security headers, XSS-безопасни шаблони
 *
 * Начален URL: http://localhost:8080
 *
 * Admin потребител (генериран при първо стартиране):
 *   email:    admin@venues.bg
 *   password: Admin1234!
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
