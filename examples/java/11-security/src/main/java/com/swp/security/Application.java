package com.swp.security;

import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.*;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.EnableWebSecurity;
import org.springframework.security.config.annotation.web.configuration.WebSecurityCustomizer;
import org.springframework.security.web.firewall.StrictHttpFirewall;
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
 * ═══════════════════════════════════════════════════════════════════
 * КАК РАБОТИ SPRING SECURITY
 * ═══════════════════════════════════════════════════════════════════
 *
 * Spring Security работи като верига от Servlet филтри (Filter Chain),
 * която се изпълнява ПРЕДИ заявката да достигне до контролера.
 *
 * Всяка HTTP заявка минава последователно през филтрите:
 *
 *   HTTP заявка
 *       │
 *       ▼
 *   ┌─────────────────────────────────────────────────────────┐
 *   │             Spring Security Filter Chain                │
 *   │                                                         │
 *   │  1. DisableEncodeUrlFilter                              │
 *   │     └─ Забранява добавянето на ;jsessionid= в URL-а    │
 *   │        (предотвратява session fixation при споделяне)   │
 *   │                                                         │
 *   │  2. SecurityContextHolderFilter                        │
 *   │     └─ Зарежда SecurityContext (кой е logged in) от    │
 *   │        сесията в ThreadLocal за текущата заявка        │
 *   │                                                         │
 *   │  3. HeaderWriterFilter                                 │
 *   │     └─ Добавя security headers към отговора:           │
 *   │        X-Frame-Options, X-Content-Type-Options, HSTS   │
 *   │                                                         │
 *   │  4. CsrfFilter                                         │
 *   │     └─ При POST/PUT/DELETE проверява CSRF токена.      │
 *   │        Ако липсва или е грешен → 403 Forbidden         │
 *   │        При GET не се проверява (safe method)           │
 *   │                                                         │
 *   │  5. LogoutFilter                                       │
 *   │     └─ Проверява дали URL е /logout.                   │
 *   │        Ако да: изчиства сесията и пренасочва           │
 *   │                                                         │
 *   │  6. UsernamePasswordAuthenticationFilter               │
 *   │     └─ Проверява дали е POST /login.                   │
 *   │        Ако да: взима username+password от формата,     │
 *   │        проверява ги спрямо UserDetailsService,         │
 *   │        ако са верни → записва Authentication в сесиита │
 *   │                                                         │
 *   │  7. AnonymousAuthenticationFilter                      │
 *   │     └─ Ако все още няма Authentication → задава        │
 *   │        анонимен потребител (не null, а "anonymousUser") │
 *   │                                                         │
 *   │  8. ExceptionTranslationFilter                        │
 *   │     └─ Хваща AuthenticationException и               │
 *   │        AccessDeniedException от следващия филтър.     │
 *   │        AuthException → redirect към /login            │
 *   │        AccessDenied (logged in, но без права) → 403   │
 *   │                                                         │
 *   │  9. AuthorizationFilter                               │
 *   │     └─ Проверява .authorizeHttpRequests() правилата.  │
 *   │        /secure/** без вход → AccessDeniedException     │
 *   │        → хваща се от филтър 8 → redirect към /login   │
 *   └─────────────────────────────────────────────────────────┘
 *       │
 *       ▼
 *   DispatcherServlet → Controller
 *
 * ═══════════════════════════════════════════════════════════════════
 * АВТЕНТИКАЦИЯ – как работи входът
 * ═══════════════════════════════════════════════════════════════════
 *
 *   POST /login (username=user&password=password)
 *       │
 *       ├─ UsernamePasswordAuthenticationFilter
 *       │       └─ loadUserByUsername("user") → UserDetails от БД/памет
 *       │       └─ passwordEncoder.matches("password", хеш) → true/false
 *       │
 *       ├─ Успех → UsernamePasswordAuthenticationToken записан в сесията
 *       │          → redirect към defaultSuccessUrl (/secure/protected)
 *       │
 *       └─ Неуспех → redirect към /login?error
 *
 *   Следваща заявка (GET /secure/protected):
 *       SecurityContextHolderFilter зарежда Authentication от JSESSIONID
 *       → AuthorizationFilter вижда authenticated() → пуска заявката
 *
 * ═══════════════════════════════════════════════════════════════════
 * ДЕМОНСТРИРАНИ АТАКИ И ЗАЩИТИ
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Атака        │ Как работи                  │ Защита в примера
 *  ─────────────┼─────────────────────────────┼──────────────────────────
 *  XSS          │ Инжектиране на <script> в   │ th:text escape-ва < > " '
 *               │ изходни данни               │ th:utext = опасно (демо)
 *  ─────────────┼─────────────────────────────┼──────────────────────────
 *  CSRF         │ Злонамерен сайт прави POST  │ CsrfFilter проверява
 *               │ към нашия сървър от браузъра│ скрит _csrf токен в форма
 *               │ на жертвата                 │ th:action го добавя авт.
 *  ─────────────┼─────────────────────────────┼──────────────────────────
 *  Clickjacking │ Нашата страница се зарежда  │ X-Frame-Options: DENY
 *               │ в <iframe> на атакуващия   │ (headerWriter конфиг)
 *  ─────────────┼─────────────────────────────┼──────────────────────────
 *  MIME sniff   │ Браузърът "познава" тип на  │ X-Content-Type-Options:
 *               │ файл вместо да вярва хедъра │ nosniff
 *  ─────────────┼─────────────────────────────┼──────────────────────────
 *  Инжектиране  │ <script src="evil.com">     │ Content-Security-Policy
 *  на ресурси   │ зарежда external скриптове  │ default-src 'self'
 *
 * ═══════════════════════════════════════════════════════════════════
 * ТЕСТ ENDPOINTS
 * ═══════════════════════════════════════════════════════════════════
 *
 *   http://localhost:8080/              – начална страница (публична)
 *   http://localhost:8080/xss-demo      – XSS демо
 *   http://localhost:8080/csrf-demo     – CSRF демо
 *   http://localhost:8080/headers-demo  – security headers демо
 *   http://localhost:8080/secure/protected – изисква вход (user/password)
 *
 * curl заявки:
 *   curl -I http://localhost:8080/                        # виж headers
 *   curl -c cookies.txt -b cookies.txt \
 *        -d "username=user&password=password" \
 *        http://localhost:8080/login                      # вход
 *   curl -b cookies.txt http://localhost:8080/secure/protected  # защитена
 *   curl -X POST http://localhost:8080/csrf-demo          # → 403 (без токен)
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

    /**
     * StrictHttpFirewall по подразбиране отхвърля не-ASCII стойности в HTTP хедъри.
     * Проблемът тук: браузърът изпраща cookie username=Студент (зададен от
     * 08-sessions на същия localhost:8080), което съдържа кирилица → 400.
     *
     * В реално приложение: cookie-тата трябва да съдържат само ASCII.
     * В демо-среда: разрешаваме всякакви стойности, за да не се блокираме.
     */
    @Bean
    WebSecurityCustomizer webSecurityCustomizer() {
        StrictHttpFirewall fw = new StrictHttpFirewall();
        fw.setAllowedHeaderValues(v -> true); // разрешава кирилица в Cookie хедъра
        return web -> web.httpFirewall(fw);
    }

    /**
     * BCryptPasswordEncoder хешира паролите с bcrypt алгоритъм.
     *
     * bcrypt включва автоматична "сол" (случайни байтове) при всяко хеширане,
     * затова encode("password") връща различен стринг при всяко извикване,
     * но matches("password", хеш) работи правилно за всички тях.
     *
     * Защо не MD5/SHA? – bcrypt е умишлено БАВЕН (cost factor),
     * което прави brute-force атаките непрактични.
     */
    @Bean
    PasswordEncoder passwordEncoder() { return new BCryptPasswordEncoder(); }

    /**
     * UserDetailsService зарежда потребителски данни по username.
     * Spring Security го извиква от UsernamePasswordAuthenticationFilter
     * при POST /login.
     *
     * InMemoryUserDetailsManager = потребителите са в RAM (за демо).
     * В реално приложение: имплементирай UserDetailsService с JPA заявка.
     *
     * Роли: ROLE_USER се добавя автоматично от roles("USER").
     * Проверка: .hasRole("USER") ≡ .hasAuthority("ROLE_USER")
     */
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
            // ── 1. CSRF ЗАЩИТА ────────────────────────────────────────────────
            // CsrfFilter генерира уникален токен за всяка сесия.
            // При всяка POST/PUT/DELETE форма Thymeleaf (th:action) добавя:
            //   <input type="hidden" name="_csrf" value="токенът">
            // Ако токенът липсва или не съвпада → 403 Forbidden.
            //
            // REST API краищата (/api/**) са изключени, защото клиентите им
            // (мобилни приложения, curl) не работят с HTML форми и сесии.
            // За REST се използва JWT/Bearer токен вместо CSRF токен.
            .csrf(csrf -> csrf
                .ignoringRequestMatchers("/api/**")
            )

            // ── 2. АВТОРИЗАЦИЯ ПО ПЪТИЩА ─────────────────────────────────────
            // Правилата се проверяват от AuthorizationFilter (последен в chain-а).
            // Оценяват се в реда, в който са написани – ПЪРВОТО съвпадение печели.
            //
            //   /secure/**  → само автентикирани потребители
            //   всичко друго → свободен достъп (permitAll)
            //
            // При неавтентикиран достъп до /secure/**:
            //   AuthorizationFilter → AccessDeniedException
            //   → ExceptionTranslationFilter → redirect 302 към /login
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/secure/**").authenticated()
                .anyRequest().permitAll()
            )

            // ── 3. ФОРМА ЗА ВХОД ─────────────────────────────────────────────
            // loginPage("/login")         – наша собствена страница (не default)
            // defaultSuccessUrl(...)      – накъде след успешен вход
            // permitAll()                 – /login е достъпна без вход
            //
            // При POST /login Spring проверява:
            //   1. Зарежда UserDetails по username
            //   2. BCrypt.matches(inputPassword, storedHash)
            //   3. Успех → Authentication в сесията → redirect
            //   4. Неуспех → redirect /login?error
            .formLogin(form -> form
                .loginPage("/login")
                .defaultSuccessUrl("/secure/protected")
                .permitAll()
            )

            // ── 4. ИЗХОД ─────────────────────────────────────────────────────
            // POST /logout (с CSRF токен):
            //   1. Изчиства SecurityContext
            //   2. Инвалидира HttpSession
            //   3. Изтрива JSESSIONID cookie от браузъра
            //   4. Пренасочва към /login?logout
            //
            // deleteCookies("JSESSIONID") → при следващо стартиране на сървъра
            // браузърът няма стара cookie → без 400 грешки
            .logout(lo -> lo
                .deleteCookies("JSESSIONID")
                .permitAll()
            )

            // ── 5. SECURITY HEADERS ──────────────────────────────────────────
            // HeaderWriterFilter добавя тези headers към ВСЕКИ отговор.
            .headers(h -> h
                // X-Frame-Options: DENY
                // Забранява зареждането на страницата в <iframe>.
                // Предотвратява clickjacking атаки.
                .frameOptions(fo -> fo.deny())

                // X-Content-Type-Options: nosniff
                // Браузърът трябва да вярва на Content-Type хедъра,
                // а не да "познава" типа на файла (MIME sniffing).
                .contentTypeOptions(ct -> {})

                // Strict-Transport-Security: max-age=31536000; includeSubDomains
                // Казва на браузъра: ползвай само HTTPS за 1 година.
                // Действа само при HTTPS (игнорира се при HTTP).
                .httpStrictTransportSecurity(hsts -> hsts
                    .includeSubDomains(true)
                    .maxAgeInSeconds(31_536_000)
                )

                // Content-Security-Policy
                // Указва от кои източници браузърът може да зарежда ресурси.
                //   default-src 'self'  – всичко само от нашия домейн
                //   style-src cdn...    – CSS от Bootstrap CDN
                //   script-src 'self'   – JS само от нас
                //   frame-ancestors 'none' – като X-Frame-Options (по-нов)
                // Нарушение → браузърът блокира ресурса (не изпълнява скрипта).
                .contentSecurityPolicy(csp -> csp
                    .policyDirectives(
                        "default-src 'self'; " +
                        "style-src 'self' https://cdn.jsdelivr.net; " +
                        "script-src 'self'; " +
                        "img-src 'self' data:; " +
                        "frame-ancestors 'none'"
                    )
                )

                // Referrer-Policy: strict-origin-when-cross-origin
                // Контролира колко информация се изпраща в Referer хедъра.
                // При cross-origin заявки: само origin (без path и query).
                // Защита: паролите/токените в URL-а не изтичат към трети страни.
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
