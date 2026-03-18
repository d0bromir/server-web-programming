package com.swp.venues.controller;

import com.swp.venues.model.Venue;
import com.swp.venues.service.VenueService;
import jakarta.servlet.http.Cookie;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.data.domain.Page;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

import java.util.*;
import java.util.stream.Collectors;

@Controller
public class HomeController {

    private static final String FAVORITES_COOKIE = "favorites";
    private final VenueService venueService;

    public HomeController(VenueService venueService) {
        this.venueService = venueService;
    }

    @GetMapping("/")
    public String index(
            @RequestParam(defaultValue = "")  String search,
            @RequestParam(defaultValue = "")  String category,
            @RequestParam(defaultValue = "0") int    page,
            @RequestParam(defaultValue = "rating") String sort,
            @RequestParam(defaultValue = "desc")   String dir,
            @CookieValue(name = FAVORITES_COOKIE, defaultValue = "") String favCookie,
            Model model) {

        // Валидиране на sort параметъра (срещу path traversal / injection)
        if (!List.of("name", "city", "rating").contains(sort)) sort = "rating";
        if (!List.of("asc", "desc").contains(dir))             dir  = "desc";
        if (page < 0)                                           page = 0;

        Page<Venue> venues = venueService.findPublic(search, category, page, sort, dir);
        Set<Long> favorites = parseFavorites(favCookie);

        model.addAttribute("venues",     venues);
        model.addAttribute("search",     search);
        model.addAttribute("category",   category);
        model.addAttribute("sort",       sort);
        model.addAttribute("dir",        dir);
        model.addAttribute("favorites",  favorites);
        model.addAttribute("categories", com.swp.venues.model.Venue.CATEGORIES);
        return "home";
    }

    @GetMapping("/favorite")
    public String toggleFavorite(
            @RequestParam Long venueId,
            @RequestParam String action,
            @CookieValue(name = FAVORITES_COOKIE, defaultValue = "") String favCookie,
            HttpServletResponse response,
            HttpServletRequest request) {

        Set<Long> favorites = new HashSet<>(parseFavorites(favCookie));
        if ("add".equals(action))    favorites.add(venueId);
        if ("remove".equals(action)) favorites.remove(venueId);

        String value = favorites.stream().map(String::valueOf).collect(Collectors.joining(","));
        Cookie cookie = new Cookie(FAVORITES_COOKIE, value);
        cookie.setMaxAge(60 * 60 * 24 * 30);  // 30 дни
        cookie.setPath("/");
        cookie.setHttpOnly(true);
        response.addCookie(cookie);

        String referer = request.getHeader("Referer");
        return "redirect:" + (referer != null ? referer : "/");
    }

    private Set<Long> parseFavorites(String cookie) {
        if (cookie == null || cookie.isBlank()) return Set.of();
        Set<Long> ids = new HashSet<>();
        for (String part : cookie.split(",")) {
            try { ids.add(Long.parseLong(part.strip())); }
            catch (NumberFormatException ignored) {}
        }
        return ids;
    }
}
