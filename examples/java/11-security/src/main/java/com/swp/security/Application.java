package com.swp.security;

import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.*;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.core.userdetails.*;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.provisioning.InMemoryUserDetailsManager;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.header.writers.ReferrerPolicyHeaderWriter;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;

/**
 * Тема 11 – Сигурност на уеб приложения
 *
 * Демонстрира:
 *   1. CSRF защита  – включена по подразбиране в Spring Security;
 *      форма без CSRF токен → 403 Forbidden
 *   2. XSS         – th:text (безопасно) vs th:utext (небезопасно)
 *   3. Security headers – CSP, X-Content-Type-Options, X-Frame-Options,
 *      Referrer-Policy, Permissions-Policy
 *   4. @Valid Bean Validation → 400 + съобщения в шаблона
 *   5. Конфигурация на Spring Security Filter Chain (разрешения по пътища)
 *
 * Тест:
 *   http://localhost:8080/
 *   http://localhost:8080/xss-demo
 *   http://localhost:8080/csrf-demo
 *   http://localhost:8080/headers-demo
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ──────────────────────────────────────────────────────────────────────
// SECURITY CONFIGURATION
// ──────────────────────────────────────────────────────────────────────
@Configuration
@EnableWebSecurity
class SecurityConfig {

    @Bean
    PasswordEncoder passwordEncoder() { return new BCryptPasswordEncoder(); }

    @Bean
    UserDetailsService users(PasswordEncoder enc) {
        UserDetails user = User.withUsername("user")
            .password(enc.encode("password"))
            .roles("USER")
            .build();
        return new InMemoryUserDetailsManager(user);
    }

    @Bean
    SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            // 1. CSRF – включена по подразбиране ─────────────────────────────
            //    Изключва се само за REST API краища (демонстративно):
            .csrf(csrf -> csrf
                .ignoringRequestMatchers("/api/**")
            )

            // 2. Разрешения по пътища ─────────────────────────────────────────
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/secure/**").authenticated()
                .anyRequest().permitAll()
            )

            // 3. Форма за вход ─────────────────────────────────────────────────
            .formLogin(form -> form
                .loginPage("/login")
                .defaultSuccessUrl("/secure/protected")
                .permitAll()
            )
            .logout(lo -> lo.permitAll())

            // 4. Security headers ─────────────────────────────────────────────
            .headers(h -> h
                // Предотвратява clickjacking
                .frameOptions(fo -> fo.deny())
                // Предотвратява MIME-sniffing
                .contentTypeOptions(ct -> {})
                // Строга транспортна сигурност (HSTS)
                .httpStrictTransportSecurity(hsts -> hsts
                    .includeSubDomains(true)
                    .maxAgeInSeconds(31_536_000)
                )
                // Content Security Policy
                .contentSecurityPolicy(csp -> csp
                    .policyDirectives(
                        "default-src 'self'; " +
                        "style-src 'self' https://cdn.jsdelivr.net; " +
                        "script-src 'self'; " +
                        "img-src 'self' data:; " +
                        "frame-ancestors 'none'"
                    )
                )
                // Referrer-Policy
                .referrerPolicy(rp -> rp
                    .policy(ReferrerPolicyHeaderWriter.ReferrerPolicy.STRICT_ORIGIN_WHEN_CROSS_ORIGIN)
                )
            );

        return http.build();
    }
}

// ──────────────────────────────────────────────────────────────────────
// DTO за демо форма
// ──────────────────────────────────────────────────────────────────────
class CommentForm {
    @NotBlank(message = "Коментарът не може да е празен")
    @Size(max = 200, message = "Максимум 200 символа")
    private String content;

    public String getContent() { return content; }
    public void setContent(String v) { this.content = v; }
}

// ──────────────────────────────────────────────────────────────────────
// CONTROLLERS
// ──────────────────────────────────────────────────────────────────────
@Controller
class DemoController {

    @GetMapping("/")
    public String index() { return "index"; }

    @GetMapping("/login")
    public String loginPage() { return "login"; }

    // ── XSS демо ──────────────────────────────────────────────────────
    @GetMapping("/xss-demo")
    public String xssDemoGet(Model m) {
        m.addAttribute("form", new CommentForm());
        return "xss-demo";
    }

    @PostMapping("/xss-demo")
    public String xssDemoPost(@Valid @ModelAttribute("form") CommentForm form,
                               BindingResult br, Model m) {
        if (!br.hasErrors()) {
            // th:text екранира < > " ' → безопасно
            m.addAttribute("safeOutput", form.getContent());
            // th:utext рендира суров HTML → уязвимо (демострационно)
            m.addAttribute("unsafeOutput", form.getContent());
        }
        return "xss-demo";
    }

    // ── CSRF демо ─────────────────────────────────────────────────────
    @GetMapping("/csrf-demo")
    public String csrfDemo() { return "csrf-demo"; }

    @PostMapping("/csrf-demo")
    public String csrfDemoPost(@RequestParam(defaultValue = "") String data,
                                Model m) {
        m.addAttribute("received", data);
        return "csrf-demo";
    }

    // ── Security headers демо ─────────────────────────────────────────
    @GetMapping("/headers-demo")
    public String headersDemo() { return "headers-demo"; }

    // ── Защитена страница (изисква вход) ─────────────────────────────
    @GetMapping("/secure/protected")
    public String protectedPage(
            org.springframework.security.core.Authentication auth,
            Model m) {
        m.addAttribute("username", auth.getName());
        return "protected";
    }
}
