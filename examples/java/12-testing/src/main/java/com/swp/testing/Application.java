package com.swp.testing;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.*;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Optional;

/**
 * Тема 12 – Тестване
 *
 * Основен код (application), придружен от тестове в:
 *   src/test/java/com/swp/testing/
 *     CalculatorTest.java      – unit тест (без Spring)
 *     VenueServiceTest.java    – unit тест с Mockito
 *     VenueControllerTest.java – интеграционен тест с MockMvc (@WebMvcTest)
 *     VenueRepositoryTest.java – тест на JPA с @DataJpaTest
 *
 * Стартиране на тестовете:
 *   mvn test
 *
 * http://localhost:8080/venues
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ──────────────────────────────────────────────────────────────────────
// ПОМОЩЕН КЛАС – Calculator (unit тест без Spring)
// ──────────────────────────────────────────────────────────────────────
@Component
class Calculator {
    public double add(double a, double b)      { return a + b; }
    public double subtract(double a, double b) { return a - b; }
    public double multiply(double a, double b) { return a * b; }
    public double divide(double a, double b) {
        if (b == 0) throw new IllegalArgumentException("Делението на нула е невъзможно.");
        return a / b;
    }
}

// ──────────────────────────────────────────────────────────────────────
// ENTITY + REPOSITORY
// ──────────────────────────────────────────────────────────────────────
@Entity
class Venue {
    @Id @GeneratedValue
    private Long id;

    @NotBlank
    private String name;

    @NotBlank
    private String city;

    @Min(1) @Max(5)
    private double rating;

    protected Venue() {}

    public Venue(String name, String city, double rating) {
        this.name = name; this.city = city; this.rating = rating;
    }

    public Long   getId()     { return id; }
    public String getName()   { return name; }
    public String getCity()   { return city; }
    public double getRating() { return rating; }
    public void setName(String v)   { this.name = v; }
    public void setCity(String v)   { this.city = v; }
    public void setRating(double v) { this.rating = v; }
}

interface VenueRepository extends JpaRepository<Venue, Long> {
    List<Venue> findByCity(String city);
    List<Venue> findByRatingGreaterThanEqual(double minRating);
}

// ──────────────────────────────────────────────────────────────────────
// SERVICE
// ──────────────────────────────────────────────────────────────────────
@Service
class VenueService {
    private final VenueRepository repo;

    VenueService(VenueRepository repo) { this.repo = repo; }

    public List<Venue> findAll()       { return repo.findAll(); }
    public Optional<Venue> findById(Long id) { return repo.findById(id); }

    public Venue create(String name, String city, double rating) {
        if (name == null || name.isBlank())
            throw new IllegalArgumentException("Името е задължително.");
        if (rating < 1 || rating > 5)
            throw new IllegalArgumentException("Рейтингът трябва да е между 1 и 5.");
        return repo.save(new Venue(name, city, rating));
    }

    public void delete(Long id) { repo.deleteById(id); }
}

// ──────────────────────────────────────────────────────────────────────
// CONTROLLER
// ──────────────────────────────────────────────────────────────────────
@Controller
@RequestMapping("/venues")
class VenueController {
    private final VenueService service;

    VenueController(VenueService service) { this.service = service; }

    @GetMapping
    public String list(Model m) {
        m.addAttribute("venues", service.findAll());
        return "venues/list";
    }

    @GetMapping("/{id}")
    public String detail(@PathVariable Long id, Model m) {
        return service.findById(id)
            .map(v -> { m.addAttribute("venue", v); return "venues/detail"; })
            .orElse("redirect:/venues");
    }
}
