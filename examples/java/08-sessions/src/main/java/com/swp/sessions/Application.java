package com.swp.sessions;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import jakarta.servlet.http.*;
import java.util.*;

/**
 * Тема 08 – Сесии и бисквитки (Sessions & Cookies)
 *
 * Демонстрира:
 *   - HttpSession – запис/четене/изтриване на атрибути
 *   - Session invalidation
 *   - HttpServletResponse.addCookie() – задаване на cookie
 *   - @CookieValue – четене на cookie от контролер
 *   - Flash messages чрез RedirectAttributes
 *   - Брояч на посещения (в сесия)
 *
 * http://localhost:8080
 *
 * curl заявки (ръчно тестване):
 *   # Запис на cookie jar за session cookie
 *   COOKIEJAR=$(mktemp /tmp/cookies-XXXX.txt)
 *
 *   # Начална страница (показва текущата сесия и cookie)
 *   curl -c "$COOKIEJAR" http://localhost:8080/
 *
 *   # Запис на атрибут в сесията
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8080/session/set \
 *        -d "key=username&value=Иван"
 *
 *   # Начална страница (сесионният атрибут се показва)
 *   curl -b "$COOKIEJAR" http://localhost:8080/
 *
 *   # Задаване на cookie
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8080/cookie/set \
 *        -d "name=theme&value=dark"
 *
 *   # Инвалидиране на сесията
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8080/session/invalidate -L
 */
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

@Controller
class SessionDemoController {

    // ── Начална страница ──────────────────────────────────────────────

    @GetMapping("/")
    public String home(HttpSession session, Model model,
                       @CookieValue(value = "username", defaultValue = "") String cookieUser) {

        // Инкрементиране на брояч в сесията
        Object attrVal = session.getAttribute("visitCount");
        int visits = (attrVal != null ? Integer.parseInt(attrVal.toString()) : 0) + 1;
        session.setAttribute("visitCount", visits);

        model.addAttribute("sessionId",    session.getId());
        model.addAttribute("visitCount",   visits);
        model.addAttribute("sessionUser",  session.getAttribute("username"));
        model.addAttribute("cookieUser",   cookieUser);
        model.addAttribute("creationTime", new Date(session.getCreationTime()));
        model.addAttribute("maxInactive",  session.getMaxInactiveInterval());

        // Всички атрибути в сесията
        var attrs = new LinkedHashMap<String, Object>();
        Collections.list(session.getAttributeNames())
            .forEach(name -> attrs.put(name, session.getAttribute(name)));
        model.addAttribute("sessionAttrs", attrs);

        return "index";
    }

    // ── Запис в сесия ─────────────────────────────────────────────────

    @PostMapping("/session/set")
    public String setSession(
            @RequestParam String key,
            @RequestParam String value,
            HttpSession session,
            RedirectAttributes flash) {

        session.setAttribute(key, value);
        flash.addFlashAttribute("success",
            "Записано в сесия: " + key + " = " + value);
        return "redirect:/";
    }

    // ── Изтриване на атрибут от сесия ────────────────────────────────

    @PostMapping("/session/remove")
    public String removeSession(
            @RequestParam String key,
            HttpSession session,
            RedirectAttributes flash) {

        session.removeAttribute(key);
        flash.addFlashAttribute("success", "Премахнато от сесия: " + key);
        return "redirect:/";
    }

    // ── Унищожаване на цялата сесия ───────────────────────────────────

    @PostMapping("/session/invalidate")
    public String invalidateSession(HttpSession session, RedirectAttributes flash) {
        session.invalidate();
        flash.addFlashAttribute("success", "Сесията беше унищожена.");
        return "redirect:/";
    }

    // ── Задаване на cookie ────────────────────────────────────────────

    @PostMapping("/cookie/set")
    public String setCookie(
            @RequestParam String name,
            @RequestParam String value,
            @RequestParam(defaultValue = "3600") int maxAge,
            HttpServletResponse response,
            RedirectAttributes flash) {

        Cookie cookie = new Cookie(name, value);
        cookie.setMaxAge(maxAge);    // секунди; -1 = session cookie
        cookie.setPath("/");
        cookie.setHttpOnly(true);    // недостъпна за JavaScript
        // cookie.setSecure(true);   // само HTTPS (в продукция)
        response.addCookie(cookie);

        flash.addFlashAttribute("success",
            "Cookie зададена: " + name + " = " + value
            + " (MaxAge: " + maxAge + "s)");
        return "redirect:/";
    }

    // ── Изтриване на cookie ───────────────────────────────────────────

    @PostMapping("/cookie/delete")
    public String deleteCookie(
            @RequestParam String name,
            HttpServletResponse response,
            RedirectAttributes flash) {

        Cookie cookie = new Cookie(name, "");
        cookie.setMaxAge(0);   // 0 = изтрий незабавно
        cookie.setPath("/");
        response.addCookie(cookie);

        flash.addFlashAttribute("success", "Cookie изтрита: " + name);
        return "redirect:/";
    }

    // ── Показване на всички cookies ────────────────────────────────────

    @GetMapping("/cookies")
    @ResponseBody
    public Map<String, String> listCookies(HttpServletRequest request) {
        var result = new LinkedHashMap<String, String>();
        if (request.getCookies() != null) {
            for (Cookie c : request.getCookies()) {
                result.put(c.getName(), c.getValue());
            }
        }
        return result;
    }
}
