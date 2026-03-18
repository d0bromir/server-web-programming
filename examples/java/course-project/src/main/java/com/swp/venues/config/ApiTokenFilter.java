package com.swp.venues.config;

import com.swp.venues.model.User;
import com.swp.venues.service.UserService;
import jakarta.servlet.*;
import jakarta.servlet.http.*;
import org.springframework.security.authentication.*;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.List;
import java.util.Optional;

/**
 * Проверява Bearer токен за REST API заявки.
 * При валиден токен задава SecurityContext автентикация.
 */
@Component
public class ApiTokenFilter extends OncePerRequestFilter {

    private final UserService userService;

    public ApiTokenFilter(UserService userService) {
        this.userService = userService;
    }

    @Override
    protected boolean shouldNotFilter(HttpServletRequest req) {
        return !req.getRequestURI().startsWith("/api/");
    }

    @Override
    protected void doFilterInternal(HttpServletRequest req,
                                    HttpServletResponse res,
                                    FilterChain chain)
            throws ServletException, IOException {

        String header = req.getHeader("Authorization");
        if (header != null && header.startsWith("Bearer ")) {
            String token = header.substring(7).strip();
            Optional<User> userOpt = userService.findByApiToken(token);
            if (userOpt.isPresent()) {
                User u = userOpt.get();
                UsernamePasswordAuthenticationToken auth =
                    new UsernamePasswordAuthenticationToken(
                        u.getEmail(), null,
                        List.of(new SimpleGrantedAuthority(u.getRole()))
                    );
                SecurityContextHolder.getContext().setAuthentication(auth);
            }
        }
        chain.doFilter(req, res);
    }
}
