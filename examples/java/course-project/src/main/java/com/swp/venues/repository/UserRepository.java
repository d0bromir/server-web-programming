package com.swp.venues.repository;

import com.swp.venues.model.User;
import org.springframework.data.jpa.repository.JpaRepository;
import java.util.Optional;

public interface UserRepository extends JpaRepository<User, Long> {
    Optional<User> findByEmail(String email);
    Optional<User> findByApiToken(String apiToken);
    boolean existsByEmail(String email);
}
