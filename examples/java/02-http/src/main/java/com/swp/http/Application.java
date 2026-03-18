package com.swp.http;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;

import java.net.URI;
import java.util.*;

/**
 * Тема 02 – HTTP протокол в Spring Boot
 *
 * Демонстрира:
 *   - HTTP методи: GET, POST, PUT, PATCH, DELETE
 *   - HTTP статус кодове с @ResponseStatus / ResponseEntity
 *   - Четене на заявка: @RequestHeader, @RequestParam, @PathVariable, @RequestBody
 *   - Задаване на response headers
 *   - 301 Redirect
 *   - Content negotiation (JSON)
 *
 * http://localhost:8080
 */
@SpringBootApplication
@RestController
public class Application {

    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }

    // ── GET ──────────────────────────────────────────────────────────

    /** GET /items → списък (200 OK) */
    @GetMapping("/items")
    public ResponseEntity<Map<String, Object>> listItems(
            @RequestParam(defaultValue = "1")  int page,
            @RequestParam(defaultValue = "10") int size) {

        var items = List.of(
            Map.of("id", 1, "name", "Ресторант Централ"),
            Map.of("id", 2, "name", "Кафе Витоша")
        );

        return ResponseEntity.ok(Map.of(
            "data",  items,
            "page",  page,
            "size",  size,
            "total", items.size()
        ));
    }

    /** GET /items/{id} → единичен ресурс (200) или 404 */
    @GetMapping("/items/{id}")
    public ResponseEntity<Map<String, Object>> getItem(@PathVariable int id) {
        if (id <= 0 || id > 100) {
            return ResponseEntity
                .status(HttpStatus.NOT_FOUND)
                .body(Map.of("error", "Не е намерено", "id", id));
        }
        return ResponseEntity.ok(Map.of("id", id, "name", "Заведение #" + id));
    }

    // ── POST ─────────────────────────────────────────────────────────

    /** POST /items → създаване (201 Created + Location header) */
    @PostMapping("/items")
    public ResponseEntity<Map<String, Object>> createItem(
            @RequestBody Map<String, String> body) {

        String name = body.getOrDefault("name", "").strip();
        if (name.isBlank()) {
            return ResponseEntity
                .status(HttpStatus.UNPROCESSABLE_ENTITY)
                .body(Map.of("error", "Полето 'name' е задължително."));
        }

        int fakeId = new Random().nextInt(1000) + 100;
        var created = Map.<String, Object>of("id", fakeId, "name", name);

        return ResponseEntity
            .created(URI.create("/items/" + fakeId))
            .body(created);
    }

    // ── PUT ──────────────────────────────────────────────────────────

    /** PUT /items/{id} → пълна замяна (200) */
    @PutMapping("/items/{id}")
    public Map<String, Object> replaceItem(
            @PathVariable int id,
            @RequestBody Map<String, String> body) {
        return Map.of("id", id, "name", body.getOrDefault("name", ""), "updated", true);
    }

    // ── PATCH ────────────────────────────────────────────────────────

    /** PATCH /items/{id} → частична промяна (200) */
    @PatchMapping("/items/{id}")
    public Map<String, Object> patchItem(
            @PathVariable int id,
            @RequestBody Map<String, Object> patches) {
        patches.put("id", id);
        patches.put("patched", true);
        return patches;
    }

    // ── DELETE ───────────────────────────────────────────────────────

    /** DELETE /items/{id} → 204 No Content */
    @DeleteMapping("/items/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void deleteItem(@PathVariable int id) {
        // В реално приложение: itemRepository.deleteById(id);
    }

    // ── Redirect ─────────────────────────────────────────────────────

    /** GET /old-items → 301 Redirect към /items */
    @GetMapping("/old-items")
    public ResponseEntity<Void> redirect() {
        return ResponseEntity
            .status(HttpStatus.MOVED_PERMANENTLY)
            .location(URI.create("/items"))
            .build();
    }

    // ── Headers ──────────────────────────────────────────────────────

    /** GET /headers → echoes request headers */
    @GetMapping("/headers")
    public ResponseEntity<Map<String, String>> echoHeaders(
            @RequestHeader Map<String, String> headers) {

        // Добавяме custom response header
        HttpHeaders responseHeaders = new HttpHeaders();
        responseHeaders.add("X-Custom-Header", "Spring-Boot-Demo");
        responseHeaders.add("X-Request-Count", "1");

        return ResponseEntity.ok()
            .headers(responseHeaders)
            .body(headers);
    }

    // ── Status codes demo ─────────────────────────────────────────────

    /** GET /status/{code} → връща зададения статус код */
    @GetMapping("/status/{code}")
    public ResponseEntity<Map<String, Object>> statusDemo(@PathVariable int code) {
        HttpStatus status;
        try {
            status = HttpStatus.valueOf(code);
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest()
                .body(Map.of("error", "Невалиден статус код: " + code));
        }
        return ResponseEntity.status(status)
            .body(Map.of(
                "status",  code,
                "reason",  status.getReasonPhrase(),
                "isError", status.isError()
            ));
    }
}
