package com.swp.frameworks;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.*;
import org.springframework.stereotype.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.filter.OncePerRequestFilter;

import jakarta.servlet.*;
import jakarta.servlet.http.*;
import java.io.IOException;
import java.util.*;

/**
 * Тема 04 – Spring Framework: IoC контейнер, DI, Beans, Middleware (Filters)
 *
 * Ключови концепции:
 *   IoC (Inversion of Control) – Spring управлява жизнения цикъл на обектите
 *   DI  (Dependency Injection) – зависимостите се инжектират автоматично
 *
 * Стереотипни анотации:
 *   @Component   – generic Spring Bean
 *   @Service     – бизнес логика
 *   @Repository  – достъп до данни
 *   @Controller  – MVC контролер
 *   @Configuration + @Bean – ръчно дефиниране на Bean
 *
 * Middleware:  OncePerRequestFilter (Filter) или HandlerInterceptor
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ── Интерфейс (абстракция) ────────────────────────────────────────────
interface GreetingService {
    String greet(String name);
}

// ── Имплементация (конкретен Bean) ────────────────────────────────────
@Service
class BulgarianGreetingService implements GreetingService {
    @Override
    public String greet(String name) {
        return "Здравей, " + name + "!";
    }
}

// ── @Configuration + @Bean ────────────────────────────────────────────
@Configuration
class AppConfig {
    /**
     * Bean, дефиниран програмно.
     * Spring ще го инжектира при нужда.
     */
    @Bean
    public Map<String, String> appMetadata() {
        return Map.of(
            "version",   "1.0.0",
            "framework", "Spring Boot 3.4.1"
        );
    }
}

// ── Middleware (Filter) ───────────────────────────────────────────────
@Component
class TimingFilter extends OncePerRequestFilter {
    @Override
    protected void doFilterInternal(HttpServletRequest req,
                                    HttpServletResponse res,
                                    FilterChain chain)
            throws ServletException, IOException {

        long start = System.currentTimeMillis();
        chain.doFilter(req, res);
        long elapsed = System.currentTimeMillis() - start;

        res.addHeader("X-Response-Time", elapsed + "ms");
    }
}

// ── Контролер (показва DI в действие) ────────────────────────────────
@RestController
@RequestMapping("/demo")
class DemoController {

    private final GreetingService greetingService;
    private final Map<String, String> metadata;

    // Constructor injection – препоръчан начин в Spring
    public DemoController(GreetingService greetingService,
                          Map<String, String> metadata) {
        this.greetingService = greetingService;
        this.metadata        = metadata;
    }

    /** GET /demo/greet?name=Иван */
    @GetMapping("/greet")
    public Map<String, String> greet(@RequestParam(defaultValue = "Студент") String name) {
        return Map.of("message", greetingService.greet(name));
    }

    /** GET /demo/beans → Meta информация (Bean от @Configuration) */
    @GetMapping("/beans")
    public Map<String, Object> beans() {
        return Map.of(
            "metadata",         metadata,
            "greetingService",  greetingService.getClass().getSimpleName(),
            "description",      "Тези обекти са управлявани от Spring IoC контейнера"
        );
    }

    /** GET /demo/container → Показва концепциите */
    @GetMapping("/container")
    public List<Map<String, String>> containerConcepts() {
        return List.of(
            Map.of("concept", "IoC",             "description", "Spring управлява обектите вместо нас"),
            Map.of("concept", "DI",              "description", "Зависимостите се инжектират автоматично"),
            Map.of("concept", "@Service",        "description", "Бизнес логика Bean"),
            Map.of("concept", "@Repository",     "description", "Данни / DB Bean"),
            Map.of("concept", "@Component",      "description", "Generic Bean"),
            Map.of("concept", "@Configuration",  "description", "Java-базирана конфигурация"),
            Map.of("concept", "Filter/Interceptor", "description", "Middleware – изпълнява се около всяка заявка")
        );
    }
}
