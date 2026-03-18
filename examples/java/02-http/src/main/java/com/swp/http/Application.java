package com.swp.http;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.http.*;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.net.URI;
import java.util.*;

/**
 * Тема 02 – HTTP протокол с HTML отговори
 *
 * Демонстрира:
 *   - GET заявки: @RequestParam (query string), @PathVariable (URL сегмент)
 *   - POST заявки и обработка на HTML форми
 *   - PRG (Post-Redirect-Get) – предотвратява дублирано изпращане при F5
 *   - HTTP пренасочвания: 301 Moved Permanently, 302 Found ("redirect:" префикс)
 *   - Четене на request headers в HTML (HttpServletRequest)
 *   - Задаване на response headers (HttpServletResponse)
 *   - HTTP статус кодове – HTML страница с произволен статус
 *
 * http://localhost:8080
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница
 *   curl http://localhost:8080/
 *
 *   # Търсене с query параметри
 *   curl "http://localhost:8080/search?q=java&city=Sofia"
 *
 *   # Елемент по id (PathVariable)
 *   curl http://localhost:8080/items/1
 *   curl http://localhost:8080/items/99   # 404
 *
 *   # Форма за добавяне – GET
 *   curl http://localhost:8080/add
 *
 *   # Добавяне – POST (PRG)
 *   curl -X POST http://localhost:8080/add \
 *        -d "name=TestItem&city=Sofia" -L
 *
 *   # Покажи request headers
 *   curl http://localhost:8080/headers
 *
 *   # Permanent redirect (301)
 *   curl -v http://localhost:8080/old-search
 *
 *   # Temporary redirect (302)
 *   curl -v http://localhost:8080/temporary
 *
 *   # Произволен HTTP статус код
 *   curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/status/418
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

@Controller
class HttpDemoController {

    private static final List<Map<String, Object>> VENUES = List.of(
        Map.of("name", "Ресторант Централ", "city", "София"),
        Map.of("name", "Кафе Витоша",       "city", "София"),
        Map.of("name", "Sky Bar",            "city", "Варна"),
        Map.of("name", "Клуб Екстрем",      "city", "Бургас")
    );

    // ── Начална страница ──────────────────────────────────────────────

    /** GET / → начална страница с линкове към всички демонстрации */
    @GetMapping("/")
    public String home() {
        return "index";
    }

    // ── GET заявки ────────────────────────────────────────────────────

    /**
     * GET /search?q=текст&city=София → страница с резултати.
     * Демонстрира @RequestParam – четене на query string параметри.
     */
    @GetMapping("/search")
    public String search(
            @RequestParam(defaultValue = "") String q,
            @RequestParam(defaultValue = "") String city,
            Model model) {
        var results = VENUES.stream()
            .filter(v ->
                (q.isBlank()    || v.get("name").toString()
                                    .toLowerCase().contains(q.toLowerCase()))
             && (city.isBlank() || v.get("city").equals(city)))
            .toList();
        model.addAttribute("q",       q);
        model.addAttribute("city",    city);
        model.addAttribute("results", results);
        return "search";
    }

    /**
     * GET /items/{id} → детайли за заведение.
     * Демонстрира @PathVariable – стойност, вградена в URL пътя.
     */
    @GetMapping("/items/{id}")
    public String item(@PathVariable int id, Model model,
                       HttpServletResponse response) {
        if (id < 1 || id > VENUES.size()) {
            response.setStatus(HttpServletResponse.SC_NOT_FOUND);
            model.addAttribute("id", id);
            return "not-found";
        }
        var venue = VENUES.get(id - 1);
        model.addAttribute("id",   id);
        model.addAttribute("name", venue.get("name"));
        model.addAttribute("city", venue.get("city"));
        return "item";
    }

    // ── POST + PRG ────────────────────────────────────────────────────

    /** GET /add → форма за добавяне на заведение */
    @GetMapping("/add")
    public String addForm() {
        return "add";
    }

    /**
     * POST /add → обработка на HTML формата с PRG шаблон.
     * PRG (Post-Redirect-Get): след успешен POST → redirect (302) → GET страница.
     * Flash атрибутите се запазват само за едно пренасочване.
     */
    @PostMapping("/add")
    public String addSubmit(
            @RequestParam String name,
            @RequestParam(defaultValue = "") String city,
            RedirectAttributes ra) {
        if (name.isBlank()) {
            ra.addFlashAttribute("error", "Името е задължително.");
            return "redirect:/add";
        }
        ra.addFlashAttribute("success",
            "Заведението \u201e" + name.strip() + "\u201c (" + city + ") беше добавено.");
        return "redirect:/";
    }

    // ── Headers ────────────────────────────────────────────────────────

    /**
     * GET /headers → показва request headers в HTML.
     * Задава custom response header (X-Demo-Header).
     */
    @GetMapping("/headers")
    public String headers(HttpServletRequest request,
                          HttpServletResponse response,
                          Model model) {
        Map<String, String> hdrs = new LinkedHashMap<>();
        Collections.list(request.getHeaderNames())
                   .forEach(h -> hdrs.put(h, request.getHeader(h)));
        response.setHeader("X-Demo-Header", "Spring-Boot-HTTP-Example");
        model.addAttribute("headers", hdrs);
        return "headers";
    }

    // ── Redirects ──────────────────────────────────────────────────────

    /**
     * GET /old-search → 301 Moved Permanently към /search.
     * Трайно пренасочване – браузърите и търсачките кешират 301.
     */
    @GetMapping("/old-search")
    public ResponseEntity<Void> permanentRedirect() {
        return ResponseEntity
            .status(HttpStatus.MOVED_PERMANENTLY)
            .location(URI.create("/search"))
            .build();
    }

    /**
     * GET /temporary → 302 Found (временно пренасочване).
     * Spring "redirect:" префикс автоматично изпраща 302.
     */
    @GetMapping("/temporary")
    public String temporaryRedirect() {
        return "redirect:/search";
    }

    // ── Status code demo ───────────────────────────────────────────────

    /**
     * GET /status/{code} → HTML страница, върната с произволен HTTP статус.
     * Демонстрира ResponseEntity с контролиран статус и HTML съдържание.
     */
    @GetMapping("/status/{code}")
    public ResponseEntity<String> statusDemo(@PathVariable int code) {
        HttpStatusCode status;
        try {
            status = HttpStatus.valueOf(code);
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest()
                .contentType(MediaType.TEXT_HTML)
                .body("<html><body><h1>Невалиден статус код: " + code
                    + "</h1><a href='/'>← Начало</a></body></html>");
        }
        String category =
            status.is2xxSuccessful()  ? "Успех (2xx)" :
            status.is3xxRedirection() ? "Пренасочване (3xx)" :
            status.is4xxClientError() ? "Грешка на клиента (4xx)" :
            status.is5xxServerError() ? "Грешка на сървъра (5xx)" :
                                        "Информационен (1xx)";
        String reasonPhrase = (status instanceof HttpStatus hs)
            ? hs.getReasonPhrase() : "Непознат";
        String html = """
            <!DOCTYPE html>
            <html lang="bg"><head><meta charset="UTF-8">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
            <title>HTTP %d</title></head>
            <body class="bg-light"><div class="container py-5">
              <h1>HTTP %d \u2013 %s</h1>
              <p class="lead">Категория: <strong>%s</strong></p>
              <a href="/" class="btn btn-secondary">\u2190 Начало</a>
            </div></body></html>
            """.formatted(code, code, reasonPhrase, category);
        return ResponseEntity.status(status)
            .contentType(MediaType.TEXT_HTML)
            .body(html);
    }
}
