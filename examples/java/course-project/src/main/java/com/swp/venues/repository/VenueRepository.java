package com.swp.venues.repository;

import com.swp.venues.model.User;
import com.swp.venues.model.Venue;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.List;

public interface VenueRepository extends JpaRepository<Venue, Long> {

    /** Публичен списък с търсене, категория и сортиране */
    @Query("""
        SELECT v FROM Venue v
        WHERE v.isPublic = true
          AND (:search IS NULL OR LOWER(v.name) LIKE LOWER(CONCAT('%',:search,'%'))
               OR LOWER(v.city) LIKE LOWER(CONCAT('%',:search,'%')))
          AND (:category IS NULL OR v.category = :category)
        """)
    Page<Venue> findPublic(
        @Param("search")   String search,
        @Param("category") String category,
        Pageable pageable
    );

    /** Заведения на конкретен потребител */
    List<Venue> findByOwner(User owner);

    /** Заведения по списък с ID (за любими) */
    List<Venue> findByIdIn(List<Long> ids);
}
