package com.swp.venues.controller;

import com.swp.venues.service.UserService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

class RegisterForm {
    @NotBlank private String name;
    @Email    private String email;
    @Size(min = 8, message = "Минимум 8 символа") private String password;

    public String getName()     { return name; }
    public String getEmail()    { return email; }
    public String getPassword() { return password; }
    public void setName(String v)     { this.name = v; }
    public void setEmail(String v)    { this.email = v; }
    public void setPassword(String v) { this.password = v; }
}

@Controller
public class AuthController {

    private final UserService userService;

    public AuthController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping("/login")
    public String loginPage() { return "login"; }

    @GetMapping("/register")
    public String registerForm(Model model) {
        model.addAttribute("form", new RegisterForm());
        return "register";
    }

    @PostMapping("/register")
    public String register(@Valid @ModelAttribute("form") RegisterForm form,
                           BindingResult br,
                           RedirectAttributes flash) {
        if (br.hasErrors()) return "register";

        try {
            userService.register(form.getName(), form.getEmail(), form.getPassword());
        } catch (IllegalArgumentException ex) {
            br.rejectValue("email", "duplicate", ex.getMessage());
            return "register";
        }

        flash.addFlashAttribute("success", "Регистрацията е успешна. Влезте в системата.");
        return "redirect:/login";
    }
}
