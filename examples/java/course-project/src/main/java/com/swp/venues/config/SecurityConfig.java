package com.swp.venues.config;

import com.swp.venues.service.UserService;
import com.swp.venues.repository.UserRepository;
import com.swp.venues.model.User;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.*;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.header.writers.ReferrerPolicyHeaderWriter;

import java.util.UUID;

@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12);
    }

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/**").permitAll()      // API защитено чрез ApiTokenFilter
                .requestMatchers("/venues/new", "/venues/*/edit", "/venues/*/delete")
                    .authenticated()
                .anyRequest().permitAll()
            )
            .formLogin(form -> form
                .loginPage("/login")
                .defaultSuccessUrl("/", true)
                .permitAll()
            )
            .logout(lo -> lo
                .logoutSuccessUrl("/?logout")
                .permitAll()
            )
            .csrf(csrf -> csrf
                .ignoringRequestMatchers("/api/**")
            )
            .headers(h -> h
                .frameOptions(fo -> fo.deny())
                .contentTypeOptions(ct -> {})
                .httpStrictTransportSecurity(hsts -> hsts
                    .includeSubDomains(true)
                    .maxAgeInSeconds(31_536_000)
                )
                .contentSecurityPolicy(csp -> csp
                    .policyDirectives(
                        "default-src 'self'; " +
                        "style-src 'self' https://cdn.jsdelivr.net; " +
                        "script-src 'self' https://cdn.jsdelivr.net; " +
                        "img-src 'self' data: https:; " +
                        "frame-ancestors 'none'"
                    )
                )
                .referrerPolicy(rp -> rp
                    .policy(ReferrerPolicyHeaderWriter.ReferrerPolicy.STRICT_ORIGIN_WHEN_CROSS_ORIGIN)
                )
            );

        return http.build();
    }

    /** Seed: admin потребител при първо стартиране */
    @Bean
    CommandLineRunner seedAdmin(UserRepository repo, PasswordEncoder enc) {
        return args -> {
            if (repo.findByEmail("admin@venues.bg").isEmpty()) {
                String token = UUID.randomUUID().toString().replace("-", "");
                repo.save(new User(
                    "Admin",
                    "admin@venues.bg",
                    enc.encode("Admin1234!"),
                    "ROLE_ADMIN",
                    token
                ));
                System.out.println("=== Admin акаунт: admin@venues.bg / Admin1234! ===");
                System.out.println("=== API токен: " + token + " ===");
            }
        };
    }
}
