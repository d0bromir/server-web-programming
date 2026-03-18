package com.swp.mvc;

import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

/**
 * Controller – приема HTTP заявки, взема данни от Service,
 * добавя ги в Model и избира View (Thymeleaf шаблон).
 *
 * @Controller – Bean управляван от Spring, рендерира HTML изгледи.
 * Model       – контейнер за данни, предавани на View-а.
 */
@Controller
public class ItemController {

    // Dependency Injection чрез конструктор (препоръчан начин)
    private final ItemService service;

    public ItemController(ItemService service) {
        this.service = service;
    }

    /** GET / → начална страница */
    @GetMapping("/")
    public String home(Model model) {
        model.addAttribute("title", "MVC Демо – Заведения");
        model.addAttribute("count", service.findAll().size());
        return "home";   // → templates/home.html
    }

    /** GET /items → списък */
    @GetMapping("/items")
    public String list(
            @RequestParam(required = false) String category,
            Model model) {

        var items = (category != null && !category.isBlank())
            ? service.findByCategory(category)
            : service.findAll();

        model.addAttribute("title",    "Заведения");
        model.addAttribute("items",    items);
        model.addAttribute("category", category);
        return "items/list";  // → templates/items/list.html
    }

    /** GET /items/{id} → детайли */
    @GetMapping("/items/{id}")
    public String detail(@PathVariable int id, Model model) {
        var item = service.findById(id);
        if (item.isEmpty()) {
            return "redirect:/items";
        }
        model.addAttribute("title", item.get().name());
        model.addAttribute("item",  item.get());
        return "items/detail";  // → templates/items/detail.html
    }
}
