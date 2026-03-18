package com.swp.database;

import jakarta.persistence.*;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.web.servlet.ServletRegistrationBean;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Service;
import org.springframework.web.bind.annotation.*;
import org.springframework.http.*;

import java.util.List;
import java.util.Optional;

/**
 * Тема 06 – База данни и ORM (Spring Data JPA + H2)
 *
 * Ключови концепции:
 *   @Entity          – клас, отразен в DB таблица
 *   @Repository      – Spring Data репозиторий (авто-генерирани CRUD методи)
 *   @Service         – бизнес логика с @Transactional
 *   JpaRepository    – наследява CrudRepository + PagingAndSortingRepository
 *   JPQL / @Query    – HQL заявки
 *   Pagination       – findAll(PageRequest.of(page, size))
 *
 * H2 конзола:  http://localhost:8080/h2-console
 *   JDBC URL:  jdbc:h2:mem:venues_db
 *
 * curl заявки (ръчно тестване):
 *   # Всички места
 *   curl http://localhost:8080/api/venues
 *
 *   # Пагинация
 *   curl "http://localhost:8080/api/venues?page=0&size=2"
 *
 *   # Търсене по град
 *   curl "http://localhost:8080/api/venues?city=Sofia"
 *
 *   # Конкретно място
 *   curl http://localhost:8080/api/venues/1
 *   curl http://localhost:8080/api/venues/999   # 404
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// H2ConsoleAutoConfiguration беше премахнат в Spring Boot 4.0 – регистрираме
// сервлета ръчно, за да работи http://localhost:8080/h2-console
@Configuration
class H2ConsoleConfig {
    @Bean
    public ServletRegistrationBean<org.h2.server.web.JakartaWebServlet> h2Console() {
        var bean = new ServletRegistrationBean<>(new org.h2.server.web.JakartaWebServlet(), "/h2-console/*");
        bean.setInitParameters(java.util.Map.of("webAllowOthers", "false"));
        bean.setLoadOnStartup(1);
        return bean;
    }
}

// ──────────────────────────────────────────────────────────────────────
// ENTITY (Model)
// ──────────────────────────────────────────────────────────────────────
@Entity
@Table(name = "venue")
class Venue {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, length = 200)
    private String name;

    @Column(nullable = false, length = 100)
    private String city;

    @Column(length = 50)
    private String category;

    @Column(columnDefinition = "TEXT")
    private String description;

    private Integer rating;

    protected Venue() {}

    public Venue(String name, String city, String category, String description, Integer rating) {
        this.name        = name;
        this.city        = city;
        this.category    = category;
        this.description = description;
        this.rating      = rating;
    }

    // Getters & setters
    public Long    getId()          { return id; }
    public String  getName()        { return name; }
    public String  getCity()        { return city; }
    public String  getCategory()    { return category; }
    public String  getDescription() { return description; }
    public Integer getRating()      { return rating; }

    public void setName(String name)               { this.name = name; }
    public void setCity(String city)               { this.city = city; }
    public void setCategory(String category)       { this.category = category; }
    public void setDescription(String description) { this.description = description; }
    public void setRating(Integer rating)          { this.rating = rating; }
}

// ──────────────────────────────────────────────────────────────────────
// REPOSITORY (Spring Data JPA автоматично генерира SQL!)
// ──────────────────────────────────────────────────────────────────────
interface VenueRepository extends JpaRepository<Venue, Long> {

    // Spring генерира: SELECT * FROM venue WHERE city = ?
    List<Venue> findByCity(String city);

    // SELECT * FROM venue WHERE category = ? ORDER BY rating DESC
    List<Venue> findByCategoryOrderByRatingDesc(String category);

    // Ръчна JPQL заявка
    @Query("SELECT v FROM Venue v WHERE LOWER(v.name) LIKE LOWER(CONCAT('%', :q, '%'))")
    List<Venue> search(@Param("q") String query);

    // Native SQL (за сложни заявки)
    @Query(value = "SELECT * FROM venue WHERE rating >= :minRating", nativeQuery = true)
    List<Venue> findHighRated(@Param("minRating") int minRating);
}

// ──────────────────────────────────────────────────────────────────────
// SERVICE
// ──────────────────────────────────────────────────────────────────────
@Service
class VenueService {

    private final VenueRepository repo;

    VenueService(VenueRepository repo) { this.repo = repo; }

    public List<Venue> findAll()                    { return repo.findAll(); }
    public Optional<Venue> findById(Long id)        { return repo.findById(id); }
    public List<Venue> findByCity(String city)      { return repo.findByCity(city); }
    public List<Venue> search(String q)             { return repo.search(q); }
    public List<Venue> findHighRated(int min)       { return repo.findHighRated(min); }

    @org.springframework.transaction.annotation.Transactional
    public Venue save(Venue venue) { return repo.save(venue); }

    @org.springframework.transaction.annotation.Transactional
    public void delete(Long id) { repo.deleteById(id); }
}

// ──────────────────────────────────────────────────────────────────────
// REST CONTROLLER
// ──────────────────────────────────────────────────────────────────────
@RestController
@RequestMapping("/api/venues")
class VenueApiController {

    private final VenueService service;
    VenueApiController(VenueService service) { this.service = service; }

    @GetMapping
    public List<Venue> list(
            @RequestParam(required = false) String search,
            @RequestParam(required = false) String city) {

        if (search != null && !search.isBlank()) return service.search(search);
        if (city   != null && !city.isBlank())   return service.findByCity(city);
        return service.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<Venue> getById(@PathVariable Long id) {
        return service.findById(id)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Venue create(@RequestBody Venue venue) {
        return service.save(new Venue(
            venue.getName(), venue.getCity(),
            venue.getCategory(), venue.getDescription(), venue.getRating()
        ));
    }

    @PutMapping("/{id}")
    public ResponseEntity<Venue> update(@PathVariable Long id, @RequestBody Venue updated) {
        return service.findById(id).map(existing -> {
            existing.setName(updated.getName());
            existing.setCity(updated.getCity());
            existing.setCategory(updated.getCategory());
            existing.setDescription(updated.getDescription());
            existing.setRating(updated.getRating());
            return ResponseEntity.ok(service.save(existing));
        }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void delete(@PathVariable Long id) { service.delete(id); }

    @GetMapping("/search")
    public List<Venue> search(@RequestParam String q) { return service.search(q); }

    @GetMapping("/high-rated")
    public List<Venue> highRated(@RequestParam(defaultValue = "4") int min) {
        return service.findHighRated(min);
    }
}
