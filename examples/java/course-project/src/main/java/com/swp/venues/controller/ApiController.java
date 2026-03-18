package com.swp.venues.controller;

import com.swp.venues.model.User;
import com.swp.venues.model.Venue;
import com.swp.venues.service.UserService;
import com.swp.venues.service.VenueService;
import jakarta.validation.Valid;
import org.springframework.http.*;
import org.springframework.security.core.Authentication;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.stream.Collectors;

record ApiResp<T>(boolean success, T data, String message) {
    static <T> ApiResp<T> ok(T data)               { return new ApiResp<>(true,  data, null); }
    static <T> ApiResp<T> ok(T data, String msg)   { return new ApiResp<>(true,  data, msg);  }
    static <T> ApiResp<T> err(String msg)           { return new ApiResp<>(false, null, msg);  }
}

@RestController
@RequestMapping("/api/venues")
public class ApiController {

    private final VenueService venueService;
    private final UserService  userService;

    public ApiController(VenueService vs, UserService us) {
        this.venueService = vs;
        this.userService  = us;
    }

    /** GET /api/venues */
    @GetMapping
    public ResponseEntity<ApiResp<List<Venue>>> list() {
        return ResponseEntity.ok(ApiResp.ok(venueService.findAll()));
    }

    /** GET /api/venues/{id} */
    @GetMapping("/{id}")
    public ResponseEntity<ApiResp<Venue>> get(@PathVariable Long id) {
        return venueService.findById(id)
            .map(v -> ResponseEntity.ok(ApiResp.ok(v)))
            .orElseGet(() -> ResponseEntity.status(HttpStatus.NOT_FOUND)
                .body(ApiResp.err("Не е намерено.")));
    }

    /** POST /api/venues – изисква Bearer токен */
    @PostMapping
    public ResponseEntity<ApiResp<Venue>> create(
            @Valid @RequestBody Venue venue, BindingResult br,
            Authentication auth) {

        if (auth == null || !auth.isAuthenticated())
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                .body(ApiResp.err("Необходим е Bearer токен."));

        if (br.hasErrors())
            return ResponseEntity.badRequest().body(
                ApiResp.err(br.getFieldErrors().stream()
                    .map(e -> e.getField() + ": " + e.getDefaultMessage())
                    .collect(Collectors.joining("; "))));

        User owner = userService.findByEmail(auth.getName()).orElse(null);
        Venue saved = venueService.create(
            venue.getName(), venue.getCity(), venue.getCategory(),
            venue.getDescription(), venue.getRating(), true, owner);

        return ResponseEntity.status(HttpStatus.CREATED)
            .body(ApiResp.ok(saved, "Създадено."));
    }

    /** DELETE /api/venues/{id} – изисква Bearer токен */
    @DeleteMapping("/{id}")
    public ResponseEntity<ApiResp<Void>> delete(
            @PathVariable Long id, Authentication auth) {

        if (auth == null || !auth.isAuthenticated())
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                .body(ApiResp.err("Необходим е Bearer токен."));

        if (venueService.findById(id).isEmpty())
            return ResponseEntity.status(HttpStatus.NOT_FOUND)
                .body(ApiResp.err("Не е намерено."));

        venueService.delete(id);
        return ResponseEntity.ok(ApiResp.ok(null, "Изтрито."));
    }
}
