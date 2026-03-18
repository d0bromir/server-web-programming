package com.swp.venues.service;

import com.swp.venues.model.User;
import com.swp.venues.repository.UserRepository;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.*;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.UUID;

@Service
public class UserService implements UserDetailsService {

    private final UserRepository repo;
    private final PasswordEncoder encoder;

    public UserService(UserRepository repo, PasswordEncoder encoder) {
        this.repo    = repo;
        this.encoder = encoder;
    }

    /** Spring Security извиква това при login */
    @Override
    public UserDetails loadUserByUsername(String email) throws UsernameNotFoundException {
        User user = repo.findByEmail(email)
            .orElseThrow(() -> new UsernameNotFoundException("Не е намерен: " + email));

        return org.springframework.security.core.userdetails.User
            .withUsername(user.getEmail())
            .password(user.getPassword())
            .authorities(new SimpleGrantedAuthority(user.getRole()))
            .build();
    }

    @Transactional
    public User register(String name, String email, String rawPassword) {
        if (repo.existsByEmail(email))
            throw new IllegalArgumentException("Имейлът вече е регистриран.");

        String apiToken = UUID.randomUUID().toString().replace("-", "");
        User user = new User(name, email, encoder.encode(rawPassword), "ROLE_USER", apiToken);
        return repo.save(user);
    }

    public java.util.Optional<User> findByEmail(String email) {
        return repo.findByEmail(email);
    }

    public java.util.Optional<User> findByApiToken(String token) {
        return repo.findByApiToken(token);
    }
}
