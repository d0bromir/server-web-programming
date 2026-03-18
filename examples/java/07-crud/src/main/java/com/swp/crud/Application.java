package com.swp.crud;

import jakarta.persistence.*;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.*;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.util.List;

/**
 * Тема 07 – CRUD операции с Spring Boot + Thymeleaf
 *
 * Демонстрира:
 *   - Три-слойна архитектура: Entity → Repository → Controller
 *   - Bean Validation (@NotBlank, @Size, @Min, @Max)
 *   - Форма с валидация (BindingResult)
 *   - Flash съобщения (RedirectAttributes)
 *   - POST / Redirect / GET патерн
 *   - Thymeleaf форма с th:object, th:field, th:errors
 *
 * http://localhost:8080/venues
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ── Entity ────────────────────────────────────────────────────────────
@Entity
@Table(name = "venue")
class Venue {

    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "Името е задължително")
    @Size(max = 200, message = "Максимум 200 символа")
    private String name;

    @NotBlank(message = "Градът е задължителен")
    private String city;

    private String category = "other";

    @Column(columnDefinition = "TEXT")
    private String description;

    @Min(value = 1, message = "Минимум 1")
    @Max(value = 5, message = "Максимум 5")
    private Integer rating;

    protected Venue() {}

    // Getters & Setters
    public Long    getId()            { return id; }
    public String  getName()          { return name; }
    public String  getCity()          { return city; }
    public String  getCategory()      { return category; }
    public String  getDescription()   { return description; }
    public Integer getRating()        { return rating; }

    public void setName(String v)        { this.name = v; }
    public void setCity(String v)        { this.city = v; }
    public void setCategory(String v)    { this.category = v; }
    public void setDescription(String v) { this.description = v; }
    public void setRating(Integer v)     { this.rating = v; }
}

// ── Repository ────────────────────────────────────────────────────────
interface VenueRepository extends JpaRepository<Venue, Long> {
    List<Venue> findAllByOrderByIdDesc();
}

// ── Controller ────────────────────────────────────────────────────────
@Controller
@RequestMapping("/venues")
class VenueController {

    private final VenueRepository repo;

    VenueController(VenueRepository repo) { this.repo = repo; }

    /** GET /venues – списък */
    @GetMapping
    public String list(Model model) {
        model.addAttribute("venues", repo.findAllByOrderByIdDesc());
        return "venues/list";
    }

    /** GET /venues/new – форма за ново заведение */
    @GetMapping("/new")
    public String newForm(Model model) {
        model.addAttribute("venue",  new Venue());
        model.addAttribute("action", "/venues");
        model.addAttribute("title",  "Ново заведение");
        return "venues/form";
    }

    /** POST /venues – запис на ново */
    @PostMapping
    public String create(@Valid @ModelAttribute Venue venue,
                         BindingResult result,
                         Model model,
                         RedirectAttributes flash) {
        if (result.hasErrors()) {
            model.addAttribute("action", "/venues");
            model.addAttribute("title",  "Ново заведение");
            return "venues/form";
        }
        repo.save(venue);
        flash.addFlashAttribute("success", "Заведението беше добавено!");
        return "redirect:/venues";
    }

    /** GET /venues/{id}/edit – форма за редакция */
    @GetMapping("/{id}/edit")
    public String editForm(@PathVariable Long id, Model model) {
        Venue venue = repo.findById(id).orElseThrow();
        model.addAttribute("venue",  venue);
        model.addAttribute("action", "/venues/" + id);
        model.addAttribute("title",  "Редакция");
        return "venues/form";
    }

    /** POST /venues/{id} – запис на редакцията */
    @PostMapping("/{id}")
    public String update(@PathVariable Long id,
                         @Valid @ModelAttribute Venue updated,
                         BindingResult result,
                         Model model,
                         RedirectAttributes flash) {
        if (result.hasErrors()) {
            model.addAttribute("action", "/venues/" + id);
            model.addAttribute("title",  "Редакция");
            return "venues/form";
        }
        Venue existing = repo.findById(id).orElseThrow();
        existing.setName(updated.getName());
        existing.setCity(updated.getCity());
        existing.setCategory(updated.getCategory());
        existing.setDescription(updated.getDescription());
        existing.setRating(updated.getRating());
        repo.save(existing);
        flash.addFlashAttribute("success", "Промените са запазени!");
        return "redirect:/venues";
    }

    /** POST /venues/{id}/delete */
    @PostMapping("/{id}/delete")
    public String delete(@PathVariable Long id, RedirectAttributes flash) {
        repo.deleteById(id);
        flash.addFlashAttribute("success", "Заведението беше изтрито.");
        return "redirect:/venues";
    }
}
