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
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница
 *   curl http://localhost:8080/
 *
 *   # Списък с елементи
 *   curl http://localhost:8080/items
 *
 *   # Детайли за елемент
 *   curl http://localhost:8080/items/1
 *   curl http://localhost:8080/items/99   # 404 Not Found
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
