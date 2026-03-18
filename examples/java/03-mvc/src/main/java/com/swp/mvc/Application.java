package com.swp.mvc;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

/**
 * Тема 03 – MVC архитектура в Spring Boot
 *
 * Структура:
 *   Model      → Item.java (Java record / POJO)
 *   View       → Thymeleaf шаблони (templates/)
 *   Controller → ItemController.java
 *
 * Стартиране: mvn spring-boot:run
 * Порт: http://localhost:8080
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
