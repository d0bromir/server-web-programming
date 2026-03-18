package com.swp.authentication;

import jakarta.persistence.*;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.*;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.stereotype.*;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.util.List;
import java.util.Optional;

/**
 * Тема 09 – Автентикация с Spring Security
 *
 * Демонстрира:
 *   - SecurityFilterChain конфигурация
 *   - UserDetailsService + BCryptPasswordEncoder
 *   - Login form / logout
 *   - Роли (ROLE_USER, ROLE_ADMIN) с @PreAuthorize
 *   - Регистрация на нов потребител
 *   - CSRF защита (включена по подразбиране в Spring Security)
 *
 * Как работи проверката на паролата (стъпка по стъпка):
 *
 *   1. Браузърът изпраща POST /login с полетата username и password.
 *      Заявката се прихваща от Spring Security – няма @PostMapping в нашия код.
 *
 *   2. UsernamePasswordAuthenticationFilter (вграден филтър) извлича
 *      username и password от заявката.
 *
 *   3. Извиква се UserDetailsService.loadUserByUsername(email):
 *        - търси потребителя в базата: SELECT * FROM app_user WHERE email = ?
 *        - връща UserDetails обект с BCrypt хеша на паролата от БД.
 *
 *   4. BCryptPasswordEncoder.matches(plainText, hashFromDb) сравнява паролите:
 *        - BCrypt извлича солта от хеша, хешира въведената парола с нея
 *          и сравнява резултата – паролата в чист текст НЕ се запазва никъде.
 *
 *   5. При успех – създава се сесия и потребителят се пренасочва към /dashboard.
 *      При грешка – пренасочване към /login?error.
 *
 *   Формат на BCrypt хеш: $2a$12$<сол><хеш>
 *     - 2a  = версия на алгоритъма
 *     - 12  = cost factor (2^12 итерации = ~250ms на съвременен CPU)
 *     - сол = 22 символа (случайна, уникална за всеки потребител)
 *
 * Акаунти след стартиране:
 *   admin@demo.com / Admin1234
 *   user@demo.com  / User1234
 *
 * http://localhost:8080
 *
 * curl заявки (ръчно тестване):
 *   # Запис на cookie jar за session cookie
 *   COOKIEJAR=$(mktemp /tmp/cookies-XXXX.txt)
 *
 *   # Login форма – GET
 *   curl http://localhost:8080/login
 *
 *   # Вход като admin (Spring Security form login – нужен е CSRF токен)
 *   # 1. Вземете CSRF токен от login страницата:
 *   CSRF=$(curl -s -c "$COOKIEJAR" http://localhost:8080/login | \
 *          grep -oP 'name="_csrf".*?value="\K[^"]+')
 *   # 2. Изпратете POST с токена:
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8080/login \
 *        -d "username=admin%40demo.com&password=Admin1234&_csrf=$CSRF" -L
 *
 *   # Dashboard (изисква автентикация)
 *   curl -b "$COOKIEJAR" http://localhost:8080/dashboard
 *
 *   # Admin страница (изисква роля ADMIN)
 *   curl -b "$COOKIEJAR" http://localhost:8080/admin/panel
 *
 *   # Изход
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8080/logout \
 *        -d "_csrf=$CSRF" -L
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// ──────────────────────────────────────────────────────────────────────
// ENTITY
// ──────────────────────────────────────────────────────────────────────
@Entity
@Table(name = "app_user")
class AppUser {
    @Id @GeneratedValue private Long id;

    @Column(unique = true, nullable = false)
    private String email;

    @Column(nullable = false)
    private String password;

    @Column(nullable = false)
    private String role = "ROLE_USER";   // ROLE_USER | ROLE_ADMIN

    private String name;

    protected AppUser() {}

    public AppUser(String email, String password, String role, String name) {
        this.email    = email;
        this.password = password;
        this.role     = role;
        this.name     = name;
    }

    public Long   getId()       { return id; }
    public String getEmail()    { return email; }
    public String getPassword() { return password; }
    public String getRole()     { return role; }
    public String getName()     { return name; }
    public void   setPassword(String p) { this.password = p; }
}

interface AppUserRepository extends JpaRepository<AppUser, Long> {
    Optional<AppUser> findByEmail(String email);
}

// ──────────────────────────────────────────────────────────────────────
// SECURITY CONFIGURATION
// ──────────────────────────────────────────────────────────────────────
@Configuration
@EnableWebSecurity
@EnableMethodSecurity
class SecurityConfig {

    private final AppUserRepository userRepo;
    SecurityConfig(AppUserRepository r) { this.userRepo = r; }

    /** Хеширане на пароли с BCrypt (cost factor 12) */
    @Bean
    PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder(12);
    }

    /**
     * UserDetailsService – Spring Security търси потребителя по username.
     * Тук username = email.
     */
    @Bean
    UserDetailsService userDetailsService(PasswordEncoder encoder) {
        return email -> {
            // Зарежда от BD при всяко login
            AppUser user = userRepo.findByEmail(email)
                .orElseThrow(() -> new UsernameNotFoundException("Не е намерен: " + email));

            return org.springframework.security.core.userdetails.User
                .withUsername(user.getEmail())
                .password(user.getPassword())
                .authorities(new SimpleGrantedAuthority(user.getRole()))
                .build();
        };
    }

    /** Правила за достъп (Security Filter Chain) */
    @Bean
    SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/", "/register", "/h2-console/**").permitAll()
                .requestMatchers("/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .formLogin(form -> form
                .loginPage("/login")
                .defaultSuccessUrl("/dashboard", true)
                .permitAll()
            )
            .logout(logout -> logout
                .logoutSuccessUrl("/?logout")
                .permitAll()
            )
            // Позволяваме H2 конзолата в dev режим
            .csrf(csrf -> csrf
                .ignoringRequestMatchers("/h2-console/**")
            )
            .headers(h -> h.frameOptions(fo -> fo.sameOrigin()));

        return http.build();
    }

    /** Seed: добавя demo потребители при стартиране */
    @Bean
    org.springframework.boot.CommandLineRunner seedUsers(PasswordEncoder enc) {
        return args -> {
            if (userRepo.count() == 0) {
                userRepo.saveAll(List.of(
                    new AppUser("admin@demo.com", enc.encode("Admin1234"), "ROLE_ADMIN", "Admin"),
                    new AppUser("user@demo.com",  enc.encode("User1234"),  "ROLE_USER",  "Потребител")
                ));
            }
        };
    }
}

// ──────────────────────────────────────────────────────────────────────
// DTO за регистрация (валидация)
// ──────────────────────────────────────────────────────────────────────
class RegisterForm {
    @NotBlank private String name;
    @Email    private String email;
    @Size(min = 8) private String password;

    public String getName()     { return name; }
    public String getEmail()    { return email; }
    public String getPassword() { return password; }
    public void setName(String v)     { this.name = v; }
    public void setEmail(String v)    { this.email = v; }
    public void setPassword(String v) { this.password = v; }
}

// ──────────────────────────────────────────────────────────────────────
// CONTROLLERS
// ──────────────────────────────────────────────────────────────────────
@Controller
class AuthController {

    private final AppUserRepository repo;
    private final PasswordEncoder   encoder;

    AuthController(AppUserRepository repo, PasswordEncoder encoder) {
        this.repo    = repo;
        this.encoder = encoder;
    }

    @GetMapping("/")
    public String home() { return "home"; }

    @GetMapping("/login")
    public String loginForm() { return "login"; }

    @GetMapping("/dashboard")
    public String dashboard(
            org.springframework.security.core.Authentication auth,
            Model model) {
        model.addAttribute("username", auth.getName());
        model.addAttribute("roles",    auth.getAuthorities());
        return "dashboard";
    }

    @GetMapping("/admin/panel")
    @org.springframework.security.access.prepost.PreAuthorize("hasRole('ADMIN')")
    public String adminPanel(Model model) {
        model.addAttribute("users", repo.findAll());
        return "admin";
    }

    @GetMapping("/register")
    public String registerForm(Model model) {
        model.addAttribute("form", new RegisterForm());
        return "register";
    }

    @PostMapping("/register")
    public String register(@Valid @ModelAttribute("form") RegisterForm form,
                           BindingResult result,
                           RedirectAttributes flash) {
        if (result.hasErrors()) return "register";

        if (repo.findByEmail(form.getEmail()).isPresent()) {
            result.rejectValue("email", "duplicate", "Имейлът вече е регистриран.");
            return "register";
        }

        repo.save(new AppUser(
            form.getEmail(),
            encoder.encode(form.getPassword()),
            "ROLE_USER",
            form.getName()
        ));

        flash.addFlashAttribute("success", "Регистрацията е успешна. Влезте в системата.");
        return "redirect:/login";
    }
}
