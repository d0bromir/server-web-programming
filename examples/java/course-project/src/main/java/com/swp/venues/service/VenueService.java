package com.swp.venues.service;

import com.swp.venues.model.User;
import com.swp.venues.model.Venue;
import com.swp.venues.repository.VenueRepository;
import org.springframework.data.domain.*;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.Optional;

@Service
public class VenueService {

    private static final int PAGE_SIZE = 6;

    private final VenueRepository repo;

    public VenueService(VenueRepository repo) { this.repo = repo; }

    public Page<Venue> findPublic(String search, String category, int page, String sortBy, String dir) {
        String searchParam   = (search   != null && !search.isBlank())   ? search   : null;
        String categoryParam = (category != null && !category.isBlank()) ? category : null;

        Sort sort = dir.equalsIgnoreCase("asc")
            ? Sort.by(sortBy).ascending()
            : Sort.by(sortBy).descending();

        Pageable pageable = PageRequest.of(page, PAGE_SIZE, sort);
        return repo.findPublic(searchParam, categoryParam, pageable);
    }

    public List<Venue> findByOwner(User owner) { return repo.findByOwner(owner); }

    public Optional<Venue> findById(Long id)   { return repo.findById(id); }

    public List<Venue> findFavorites(List<Long> ids) {
        if (ids == null || ids.isEmpty()) return List.of();
        return repo.findByIdIn(ids);
    }

    @Transactional
    public Venue create(String name, String city, String category,
                        String description, double rating, boolean isPublic, User owner) {
        return repo.save(new Venue(name, city, category, description, rating, isPublic, owner));
    }

    @Transactional
    public Venue update(Venue venue, String name, String city, String category,
                        String description, double rating, boolean isPublic) {
        venue.setName(name);
        venue.setCity(city);
        venue.setCategory(category);
        venue.setDescription(description);
        venue.setRating(rating);
        venue.setPublic(isPublic);
        return repo.save(venue);
    }

    @Transactional
    public void delete(Long id) { repo.deleteById(id); }

    public List<Venue> findAll() { return repo.findAll(); }

    public Venue save(Venue v)   { return repo.save(v); }
}
