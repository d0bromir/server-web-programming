package com.swp.introduction;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

import java.time.LocalDateTime;
import java.util.List;
import java.util.Map;

/**
 * Тема 01 – Въведение в Spring Boot
 *
 * Основни концепции:
 *   @SpringBootApplication  = @Configuration + @EnableAutoConfiguration + @ComponentScan
 *   @Controller             = MVC контролер – рендерира Thymeleaf HTML шаблони
 *   @GetMapping("/path")    = съкратено за @RequestMapping(method = GET)
 *   Model                   = предава данни от контролера към шаблона
 *
 * Поток на заявката (MVC):
 *   Браузър → GET / → HomeController.home() → Model → Thymeleaf → index.html → HTML отговор
 *
 * @Controller  → методът връща String (иmе на шаблон) → Thymeleaf генерира HTML
 * @RestController → директен JSON/текст отговор (вж. тема 10 – REST API)
 *
 * Стартиране:
 *   mvn spring-boot:run
 *   или: mvn package && java -jar target/01-introduction-1.0.0.jar
 *
 * Порт: http://localhost:8080
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница
 *   curl http://localhost:8080/
 *
 *   # Поздрав с параметър
 *   curl "http://localhost:8080/greet?name=World"
 *
 *   # За страница
 *   curl http://localhost:8080/about
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

@Controller
class HomeController {

    private static final List<Map<String, Object>> VENUES = List.of(
        Map.of("id", 1, "name", "Библиотеката",   "city", "София",   "category", "cafe"),
        Map.of("id", 2, "name", "Червената Стая", "city", "Варна",   "category", "restaurant"),
        Map.of("id", 3, "name", "Sky Bar",         "city", "Пловдив", "category", "bar")
    );

    /** GET / → начална страница */
    @GetMapping("/")
    public String home(Model model) {
        model.addAttribute("title",       "Любими заведения");
        model.addAttribute("framework",   "Spring Boot 4.0.0");
        model.addAttribute("javaVersion", System.getProperty("java.version"));
        model.addAttribute("time",        LocalDateTime.now());
        model.addAttribute("venues",      VENUES);
        return "index";   // → src/main/resources/templates/index.html
    }

    /** GET /greet?name=Иван → персонален HTML поздрав */
    @GetMapping("/greet")
    public String greet(
            @RequestParam(defaultValue = "Студент") String name,
            Model model) {
        model.addAttribute("name",    name);
        model.addAttribute("message", "Здравей, " + name + "! Добре дошъл в Spring Boot.");
        return "greet";   // → templates/greet.html
    }

    /** GET /about → страница „за приложението" */
    @GetMapping("/about")
    public String about(Model model) {
        model.addAttribute("framework",   "Spring Boot 4.0.0");
        model.addAttribute("javaVersion", System.getProperty("java.version"));
        model.addAttribute("description",
            "Примерно Spring Boot приложение за любими заведения, рендерирано с Thymeleaf.");
        return "about";   // → templates/about.html
    }
}
