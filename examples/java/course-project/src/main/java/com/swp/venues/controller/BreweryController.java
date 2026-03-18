package com.swp.venues.controller;

import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.client.RestTemplate;

import java.util.List;
import java.util.Map;

@Controller
public class BreweryController {

    private static final String BREWERY_API =
        "https://api.openbrewerydb.org/v1/breweries?per_page=10";

    @GetMapping("/brewery")
    @SuppressWarnings("unchecked")
    public String index(
            @RequestParam(defaultValue = "") String city,
            Model model) {

        String url = city.isBlank()
            ? BREWERY_API
            : BREWERY_API + "&by_city=" + city.replace(" ", "_");

        List<Map<String, Object>> breweries = null;
        String error = null;

        try {
            RestTemplate restTemplate = new RestTemplate();
            breweries = restTemplate.getForObject(url, List.class);
        } catch (Exception ex) {
            error = "Грешка при зареждане на данните: " + ex.getMessage();
        }

        model.addAttribute("breweries", breweries);
        model.addAttribute("error",     error);
        model.addAttribute("city",      city);
        return "brewery/index";
    }
}
