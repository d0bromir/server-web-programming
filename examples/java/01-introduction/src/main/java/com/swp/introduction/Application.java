package com.swp.introduction;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.web.bind.annotation.*;
import org.springframework.stereotype.*;
import org.springframework.ui.Model;

import java.time.LocalDateTime;
import java.util.Map;

/**
 * Тема 01 – Въведение в Spring Boot
 *
 * Основни концепции:
 *   @SpringBootApplication  = @Configuration + @EnableAutoConfiguration + @ComponentScan
 *   @RestController         = @Controller + @ResponseBody (връща JSON/текст директно)
 *   @Controller             = MVC контролер (рендерира Thymeleaf шаблони)
 *   @GetMapping("/path")    = съкратено за @RequestMapping(method=GET)
 *
 * Стартиране:
 *   mvn spring-boot:run
 *   или: mvn package && java -jar target/01-introduction-1.0.0.jar
 *
 * Порт: http://localhost:8080
 */
@SpringBootApplication
public class Application {

    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }

    // ── REST контролер – връща JSON / текст ──────────────────────────
    @RestController
    @RequestMapping("/api")
    static class InfoController {

        /** GET /api/info → JSON с информация за приложението */
        @GetMapping("/info")
        public Map<String, Object> info() {
            return Map.of(
                "app",       "Любими заведения",
                "version",   "1.0.0",
                "framework", "Spring Boot 3.4.1",
                "java",      System.getProperty("java.version"),
                "time",      LocalDateTime.now().toString()
            );
        }

        /** GET /api/hello?name=Иван → "Здравей, Иван!" */
        @GetMapping("/hello")
        public Map<String, String> hello(
                @RequestParam(defaultValue = "свят") String name) {
            return Map.of("message", "Здравей, " + name + "!");
        }

        /** GET /api/request-info → информация за HTTP заявката */
        @GetMapping("/request-info")
        public Map<String, String> requestInfo(
                jakarta.servlet.http.HttpServletRequest request) {
            return Map.of(
                "method",      request.getMethod(),
                "uri",         request.getRequestURI(),
                "remoteAddr",  request.getRemoteAddr(),
                "userAgent",   request.getHeader("User-Agent")
            );
        }
    }

    // ── MVC контролер – рендерира Thymeleaf шаблон ───────────────────
    @Controller
    static class HomeController {

        /** GET / → рендерира views/index.html */
        @GetMapping("/")
        public String index(Model model) {
            model.addAttribute("title",      "Въведение в Spring Boot");
            model.addAttribute("framework",  "Spring Boot 3.4.1");
            model.addAttribute("javaVersion", System.getProperty("java.version"));
            model.addAttribute("time",       LocalDateTime.now());
            return "index";    // → src/main/resources/templates/index.html
        }
    }
}
