package com.swp.routing;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;

import java.util.*;

/**
 * Тема 05 – Маршрутизиране в Spring Boot
 *
 * Демонстрира:
 *   @RequestMapping  – общ маршрут за клас
 *   @GetMapping, @PostMapping, @PutMapping, @DeleteMapping
 *   @PathVariable    – URL параметри  /venues/{id}
 *   @RequestParam    – query параметри  ?search=xxx&page=1
 *   Wildcard маршрути /api/** 
 *   Regex ограничения /items/{id:[0-9]+}
 *   Множество маршрути на един метод
 *
 * Стартиране: mvn spring-boot:run → http://localhost:8080
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ── Основен маршрутизационен контролер ───────────────────────────────
@RestController
@RequestMapping("/venues")   // ← общ prefix за всички маршрути в класа
class VenueRoutingController {

    private static final List<Map<String, Object>> DATA = List.of(
        Map.of("id", 1, "name", "Cafe Sofia",   "city", "Sofia"),
        Map.of("id", 2, "name", "Bar Plovdiv",  "city", "Plovdiv"),
        Map.of("id", 3, "name", "Hotel Varna",  "city", "Varna")
    );

    // ── GET /venues ───────────────────────────────────────────────────

    /** query params: ?search=&city=&page=1&size=10 */
    @GetMapping
    public Map<String, Object> list(
            @RequestParam(defaultValue = "") String search,
            @RequestParam(defaultValue = "") String city,
            @RequestParam(defaultValue = "1")  int page,
            @RequestParam(defaultValue = "10") int size) {

        var filtered = DATA.stream()
            .filter(v -> search.isBlank() ||
                         v.get("name").toString().toLowerCase().contains(search.toLowerCase()))
            .filter(v -> city.isBlank() ||
                         v.get("city").toString().equalsIgnoreCase(city))
            .toList();

        return Map.of(
            "data",   filtered,
            "total",  filtered.size(),
            "page",   page,
            "_links", Map.of("self", "/venues?page=" + page)
        );
    }

    // ── GET /venues/{id} с regex – само числа ─────────────────────────

    @GetMapping("/{id:[0-9]+}")
    public ResponseEntity<Map<String, Object>> getById(@PathVariable int id) {
        return DATA.stream()
            .filter(v -> ((int) v.get("id")) == id)
            .findFirst()
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    // ── POST /venues ──────────────────────────────────────────────────

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Map<String, Object> create(@RequestBody Map<String, String> body) {
        return Map.of("id", 999, "name", body.getOrDefault("name", ""), "created", true);
    }

    // ── Специален маршрут /venues/search ─────────────────────────────
    // Spring реши конфликта: по-специфичните пътища имат приоритет

    @GetMapping("/search")
    public Map<String, Object> search(@RequestParam String q) {
        var results = DATA.stream()
            .filter(v -> v.get("name").toString().toLowerCase().contains(q.toLowerCase()))
            .toList();
        return Map.of("query", q, "results", results);
    }
}

// ── Wildcard маршрути ─────────────────────────────────────────────────
@RestController
class WildcardController {

    /** Хваща /api/v1/..., /api/v2/... и т.н. */
    @GetMapping("/api/**")
    public Map<String, String> apiCatchAll(jakarta.servlet.http.HttpServletRequest req) {
        return Map.of(
            "path",    req.getRequestURI(),
            "message", "Wildcard маршрут: /api/**"
        );
    }
}

// ── Множество маршрути на един метод ─────────────────────────────────
@RestController
class AliasController {

    @GetMapping({"", "/", "/home", "/index"})
    public Map<String, String> home() {
        return Map.of(
            "message", "Начална страница",
            "routes",  "/, /home, /index – всички водят тук"
        );
    }
}
