package com.swp.venues.controller;

import com.swp.venues.model.User;
import com.swp.venues.model.Venue;
import com.swp.venues.service.UserService;
import com.swp.venues.service.VenueService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.security.access.AccessDeniedException;
import org.springframework.security.core.Authentication;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

class VenueForm {
    @NotBlank(message = "Името е задължително")
    @Size(max = 100)
    private String name;

    @NotBlank(message = "Градът е задължителен")
    @Size(max = 100)
    private String city;

    @NotBlank(message = "Категорията е задължителна")
    private String category;

    @Size(max = 500)
    private String description;

    @Min(1) @Max(5)
    private double rating = 3.0;

    private boolean isPublic = true;

    public String  getName()        { return name; }
    public String  getCity()        { return city; }
    public String  getCategory()    { return category; }
    public String  getDescription() { return description; }
    public double  getRating()      { return rating; }
    public boolean isPublic()       { return isPublic; }

    public void setName(String v)        { this.name = v; }
    public void setCity(String v)        { this.city = v; }
    public void setCategory(String v)    { this.category = v; }
    public void setDescription(String v) { this.description = v; }
    public void setRating(double v)      { this.rating = v; }
    public void setPublic(boolean v)     { this.isPublic = v; }
}

@Controller
@RequestMapping("/venues")
public class VenueController {

    private final VenueService venueService;
    private final UserService  userService;

    public VenueController(VenueService venueService, UserService userService) {
        this.venueService = venueService;
        this.userService  = userService;
    }

    /** GET /venues – списък на моите заведения */
    @GetMapping
    public String index(Authentication auth, Model model) {
        User user = resolveUser(auth);
        boolean isAdmin = auth.getAuthorities().stream()
            .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"));

        model.addAttribute("venues", isAdmin
            ? venueService.findAll()
            : venueService.findByOwner(user));
        return "venues/index";
    }

    /** GET /venues/new */
    @GetMapping("/new")
    public String newForm(Model model) {
        model.addAttribute("form",       new VenueForm());
        model.addAttribute("categories", Venue.CATEGORIES);
        model.addAttribute("action",     "/venues");
        return "venues/form";
    }

    /** POST /venues */
    @PostMapping
    public String create(@Valid @ModelAttribute("form") VenueForm form,
                         BindingResult br, Authentication auth,
                         Model model, RedirectAttributes flash) {
        if (br.hasErrors()) {
            model.addAttribute("categories", Venue.CATEGORIES);
            model.addAttribute("action",     "/venues");
            return "venues/form";
        }
        User user = resolveUser(auth);
        venueService.create(form.getName(), form.getCity(), form.getCategory(),
            form.getDescription(), form.getRating(), form.isPublic(), user);
        flash.addFlashAttribute("success", "Заведението е добавено.");
        return "redirect:/venues";
    }

    /** GET /venues/{id}/edit */
    @GetMapping("/{id}/edit")
    public String editForm(@PathVariable Long id, Authentication auth, Model model) {
        Venue venue = loadAndCheckOwner(id, auth);

        VenueForm form = new VenueForm();
        form.setName(venue.getName());
        form.setCity(venue.getCity());
        form.setCategory(venue.getCategory());
        form.setDescription(venue.getDescription());
        form.setRating(venue.getRating());
        form.setPublic(venue.isPublic());

        model.addAttribute("form",       form);
        model.addAttribute("categories", Venue.CATEGORIES);
        model.addAttribute("venueId",    id);
        model.addAttribute("action",     "/venues/" + id);
        return "venues/form";
    }

    /** POST /venues/{id}  (метод-override чрез hidden _method=PUT) */
    @PostMapping("/{id}")
    public String update(@PathVariable Long id,
                         @Valid @ModelAttribute("form") VenueForm form,
                         BindingResult br, Authentication auth,
                         Model model, RedirectAttributes flash) {
        if (br.hasErrors()) {
            model.addAttribute("categories", Venue.CATEGORIES);
            model.addAttribute("venueId",    id);
            model.addAttribute("action",     "/venues/" + id);
            return "venues/form";
        }
        Venue venue = loadAndCheckOwner(id, auth);
        venueService.update(venue, form.getName(), form.getCity(), form.getCategory(),
            form.getDescription(), form.getRating(), form.isPublic());
        flash.addFlashAttribute("success", "Заведението е обновено.");
        return "redirect:/venues";
    }

    /** POST /venues/{id}/delete */
    @PostMapping("/{id}/delete")
    public String delete(@PathVariable Long id,
                         Authentication auth,
                         RedirectAttributes flash) {
        loadAndCheckOwner(id, auth);
        venueService.delete(id);
        flash.addFlashAttribute("success", "Заведението е изтрито.");
        return "redirect:/venues";
    }

    // ── Помощни методи ────────────────────────────────────────────────

    private User resolveUser(Authentication auth) {
        return userService.findByEmail(auth.getName())
            .orElseThrow(() -> new IllegalStateException("Потребителят не е намерен."));
    }

    private Venue loadAndCheckOwner(Long id, Authentication auth) {
        Venue venue = venueService.findById(id)
            .orElseThrow(() -> new IllegalArgumentException("Заведението не е намерено."));

        boolean isAdmin = auth.getAuthorities().stream()
            .anyMatch(a -> a.getAuthority().equals("ROLE_ADMIN"));
        boolean isOwner = venue.getOwner() != null &&
            venue.getOwner().getEmail().equals(auth.getName());

        if (!isAdmin && !isOwner)
            throw new AccessDeniedException("Нямате достъп до това заведение.");

        return venue;
    }
}
