package com.swp.restapi;

import jakarta.servlet.*;
import jakarta.servlet.http.*;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.data.domain.*;
import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.filter.OncePerRequestFilter;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.io.IOException;
import java.util.*;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.atomic.AtomicLong;
import java.util.stream.Collectors;

/**
 * Тема 10 – REST API
 *
 * Демонстрира:
 *   - @RestController, ResponseEntity, HTTP статус кодове
 *   - Стандартен API отговор ApiResponse<T>
 *   - CRUD за ресурс Venue (съхранение в памет – без БД)
 *   - Пагинация (/api/venues?page=0&size=3)
 *   - Bearer-token автентикация чрез OncePerRequestFilter
 *   - @ExceptionHandler / @ControllerAdvice за глобална обработка на грешки
 *   - @Valid Bean Validation → 400 Bad Request
 *
 * Тест с curl:
 *   # GET всички
 *   curl http://localhost:8080/api/venues
 *
 *   # GET с пагинация
 *   curl "http://localhost:8080/api/venues?page=0&size=2"
 *
 *   # GET по id
 *   curl http://localhost:8080/api/venues/1
 *
 *   # POST (изисква Bearer токен)
 *   curl -X POST http://localhost:8080/api/venues \
 *        -H "Authorization: Bearer secret-token" \
 *        -H "Content-Type: application/json" \
 *        -d '{"name":"Ателие","city":"Пловдив","category":"cafe","rating":4.5}'
 *
 *   # PUT
 *   curl -X PUT http://localhost:8080/api/venues/1 \
 *        -H "Authorization: Bearer secret-token" \
 *        -H "Content-Type: application/json" \
 *        -d '{"name":"Ново Ателие","city":"Пловдив","category":"cafe","rating":4.8}'
 *
 *   # DELETE
 *   curl -X DELETE http://localhost:8080/api/venues/1 \
 *        -H "Authorization: Bearer secret-token"
 *
 * http://localhost:8080/api/venues
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ──────────────────────────────────────────────────────────────────────
// СТАНДАРТЕН API ОТГОВОР
// ──────────────────────────────────────────────────────────────────────

/** Обгръща всички отговори в { success, data, message } */
record ApiResponse<T>(boolean success, T data, String message) {

    static <T> ApiResponse<T> ok(T data) {
        return new ApiResponse<>(true, data, null);
    }

    static <T> ApiResponse<T> ok(T data, String message) {
        return new ApiResponse<>(true, data, message);
    }

    static <T> ApiResponse<T> error(String message) {
        return new ApiResponse<>(false, null, message);
    }
}

/** Страница с пагинация */
record PagedResponse<T>(List<T> content, int page, int size, long totalElements, int totalPages) {}

// ──────────────────────────────────────────────────────────────────────
// МОДЕЛ
// ──────────────────────────────────────────────────────────────────────
class Venue {
    private Long   id;
    @NotBlank(message = "Името е задължително")
    private String name;
    @NotBlank(message = "Градът е задължителен")
    private String city;
    @NotBlank(message = "Категорията е задължителна")
    private String category;
    @Min(value = 1, message = "Рейтингът трябва да е между 1 и 5")
    @Max(value = 5, message = "Рейтингът трябва да е между 1 и 5")
    private double rating;

    public Venue() {}
    public Venue(Long id, String name, String city, String category, double rating) {
        this.id = id; this.name = name; this.city = city;
        this.category = category; this.rating = rating;
    }

    public Long   getId()       { return id; }
    public String getName()     { return name; }
    public String getCity()     { return city; }
    public String getCategory() { return category; }
    public double getRating()   { return rating; }
    public void setId(Long v)       { this.id = v; }
    public void setName(String v)   { this.name = v; }
    public void setCity(String v)   { this.city = v; }
    public void setCategory(String v){ this.category = v; }
    public void setRating(double v) { this.rating = v; }
}

// ──────────────────────────────────────────────────────────────────────
// УСЛУГА (in-memory store)
// ──────────────────────────────────────────────────────────────────────
@Service
class VenueService {
    private final Map<Long, Venue> store = new ConcurrentHashMap<>();
    private final AtomicLong idSeq = new AtomicLong(1);

    VenueService() {
        save(new Venue(null, "Библиотеката", "София",  "cafe",       4.7));
        save(new Venue(null, "Червената Стая", "Варна", "restaurant", 4.4));
        save(new Venue(null, "Sky Bar",       "Пловдив","bar",        4.2));
        save(new Venue(null, "Club Extreme",  "Бургас", "club",       3.9));
        save(new Venue(null, "Кулата",        "Велико Търново","restaurant",4.6));
    }

    public List<Venue> findAll() {
        return new ArrayList<>(store.values());
    }

    public PagedResponse<Venue> findAll(int page, int size) {
        List<Venue> all = findAll();
        int total = all.size();
        int totalPages = (int) Math.ceil((double) total / size);
        List<Venue> content = all.stream()
            .skip((long) page * size)
            .limit(size)
            .collect(Collectors.toList());
        return new PagedResponse<>(content, page, size, total, totalPages);
    }

    public Optional<Venue> findById(Long id) { return Optional.ofNullable(store.get(id)); }

    public Venue save(Venue v) {
        if (v.getId() == null) v.setId(idSeq.getAndIncrement());
        store.put(v.getId(), v);
        return v;
    }

    public Optional<Venue> update(Long id, Venue data) {
        if (!store.containsKey(id)) return Optional.empty();
        data.setId(id);
        store.put(id, data);
        return Optional.of(data);
    }

    public boolean delete(Long id) {
        return store.remove(id) != null;
    }
}

// ──────────────────────────────────────────────────────────────────────
// BEARER TOKEN FILTER
// ──────────────────────────────────────────────────────────────────────

/**
 * Проверява "Authorization: Bearer <token>" за write операции.
 * В реален проект токенът се валидира срещу БД или JWT библиотека.
 */
@Configuration
class SecurityConfig {
    static final String VALID_TOKEN = "secret-token";

    @Bean
    public Filter apiTokenFilter() {
        return new OncePerRequestFilter() {
            @Override
            protected void doFilterInternal(HttpServletRequest req,
                                            HttpServletResponse res,
                                            FilterChain chain)
                    throws ServletException, IOException {

                String method = req.getMethod();
                String path   = req.getRequestURI();

                boolean isWriteOperation =
                    (method.equals("POST") || method.equals("PUT") || method.equals("DELETE"))
                    && path.startsWith("/api/");

                if (isWriteOperation) {
                    String header = req.getHeader("Authorization");
                    if (header == null || !header.startsWith("Bearer ")) {
                        res.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
                        res.setContentType("application/json;charset=UTF-8");
                        res.getWriter().write(
                            "{\"success\":false,\"data\":null," +
                            "\"message\":\"Необходим е Bearer токен.\"}");
                        return;
                    }
                    String token = header.substring(7).strip();
                    // Constant-time comparison prevents timing attacks
                    if (!MessageDigest.isEqual(
                            token.getBytes(), VALID_TOKEN.getBytes())) {
                        res.setStatus(HttpServletResponse.SC_FORBIDDEN);
                        res.setContentType("application/json;charset=UTF-8");
                        res.getWriter().write(
                            "{\"success\":false,\"data\":null," +
                            "\"message\":\"Невалиден токен.\"}");
                        return;
                    }
                }
                chain.doFilter(req, res);
            }
        };
    }
}

// We need java.security.MessageDigest:
import java.security.MessageDigest;

// ──────────────────────────────────────────────────────────────────────
// CONTROLLER
// ──────────────────────────────────────────────────────────────────────
@RestController
@RequestMapping("/api/venues")
class VenueController {

    private final VenueService service;
    VenueController(VenueService s) { this.service = s; }

    /** GET /api/venues?page=0&size=5 */
    @GetMapping
    public ResponseEntity<ApiResponse<PagedResponse<Venue>>> list(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "5") int size) {

        if (size < 1 || size > 100) size = 5;
        return ResponseEntity.ok(ApiResponse.ok(service.findAll(page, size)));
    }

    /** GET /api/venues/{id} */
    @GetMapping("/{id}")
    public ResponseEntity<ApiResponse<Venue>> get(@PathVariable Long id) {
        return service.findById(id)
            .map(v -> ResponseEntity.ok(ApiResponse.ok(v)))
            .orElseGet(() -> ResponseEntity.status(HttpStatus.NOT_FOUND)
                .body(ApiResponse.error("Заведение #" + id + " не е намерено.")));
    }

    /** POST /api/venues  (изисква Bearer токен) */
    @PostMapping
    public ResponseEntity<ApiResponse<Venue>> create(
            @Valid @RequestBody Venue venue,
            BindingResult br) {

        if (br.hasErrors()) {
            String msg = br.getFieldErrors().stream()
                .map(e -> e.getField() + ": " + e.getDefaultMessage())
                .collect(Collectors.joining("; "));
            return ResponseEntity.badRequest().body(ApiResponse.error(msg));
        }
        Venue saved = service.save(venue);
        return ResponseEntity.status(HttpStatus.CREATED)
            .body(ApiResponse.ok(saved, "Заведението е създадено."));
    }

    /** PUT /api/venues/{id}  (изисква Bearer токен) */
    @PutMapping("/{id}")
    public ResponseEntity<ApiResponse<Venue>> update(
            @PathVariable Long id,
            @Valid @RequestBody Venue venue,
            BindingResult br) {

        if (br.hasErrors()) {
            String msg = br.getFieldErrors().stream()
                .map(e -> e.getField() + ": " + e.getDefaultMessage())
                .collect(Collectors.joining("; "));
            return ResponseEntity.badRequest().body(ApiResponse.error(msg));
        }
        return service.update(id, venue)
            .map(v -> ResponseEntity.ok(ApiResponse.ok(v, "Обновено успешно.")))
            .orElseGet(() -> ResponseEntity.status(HttpStatus.NOT_FOUND)
                .body(ApiResponse.error("Заведение #" + id + " не е намерено.")));
    }

    /** DELETE /api/venues/{id}  (изисква Bearer токен) */
    @DeleteMapping("/{id}")
    public ResponseEntity<ApiResponse<Void>> delete(@PathVariable Long id) {
        if (service.delete(id)) {
            return ResponseEntity.ok(ApiResponse.ok(null, "Изтрито успешно."));
        }
        return ResponseEntity.status(HttpStatus.NOT_FOUND)
            .body(ApiResponse.error("Заведение #" + id + " не е намерено."));
    }
}

// ──────────────────────────────────────────────────────────────────────
// ГЛОБАЛНА ОБРАБОТКА НА ГРЕШКИ
// ──────────────────────────────────────────────────────────────────────
@RestControllerAdvice
class GlobalExceptionHandler {

    /** 400 при невалидни JSON данни (HttpMessageNotReadableException) */
    @ExceptionHandler(org.springframework.http.converter.HttpMessageNotReadableException.class)
    public ResponseEntity<ApiResponse<Void>> handleBadJson() {
        return ResponseEntity.badRequest()
            .body(ApiResponse.error("Невалидно тяло на заявката (невалиден JSON)."));
    }

    /** 405 Method Not Allowed */
    @ExceptionHandler(org.springframework.web.HttpRequestMethodNotSupportedException.class)
    public ResponseEntity<ApiResponse<Void>> handleMethodNotAllowed(
            org.springframework.web.HttpRequestMethodNotSupportedException ex) {
        return ResponseEntity.status(HttpStatus.METHOD_NOT_ALLOWED)
            .body(ApiResponse.error("Методът " + ex.getMethod() + " не е позволен."));
    }

    /** 500 за неочаквани грешки */
    @ExceptionHandler(Exception.class)
    public ResponseEntity<ApiResponse<Void>> handleGeneral(Exception ex) {
        return ResponseEntity.internalServerError()
            .body(ApiResponse.error("Вътрешна грешка: " + ex.getMessage()));
    }
}
