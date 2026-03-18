package com.swp.security;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.security.test.context.support.WithMockUser;
import org.springframework.security.test.web.servlet.setup.SecurityMockMvcConfigurers;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest
class ApplicationTest {

    @Autowired
    WebApplicationContext wac;

    MockMvc mvc;

    @BeforeEach
    void setUp() {
        mvc = MockMvcBuilders.webAppContextSetup(wac)
                .apply(SecurityMockMvcConfigurers.springSecurity())
                .build();
    }

    // ── Публични страници ───────────────────────────────────────────────

    @Test
    void homePageReturnsOk() throws Exception {
        mvc.perform(get("/"))
                .andExpect(status().isOk());
    }

    @Test
    void loginPageReturnsOk() throws Exception {
        mvc.perform(get("/login"))
                .andExpect(status().isOk());
    }

    @Test
    void xssDemoGetReturnsOk() throws Exception {
        mvc.perform(get("/xss-demo"))
                .andExpect(status().isOk());
    }

    @Test
    void csrfDemoGetReturnsOk() throws Exception {
        mvc.perform(get("/csrf-demo"))
                .andExpect(status().isOk());
    }

    @Test
    void headersDemoReturnsOk() throws Exception {
        mvc.perform(get("/headers-demo"))
                .andExpect(status().isOk());
    }

    // ── Защитена страница изисква вход ──────────────────────────────────

    @Test
    void protectedPageAnonymousRedirectsToLogin() throws Exception {
        mvc.perform(get("/secure/protected"))
                .andExpect(status().is3xxRedirection());
    }

    @Test
    @WithMockUser(username = "user", roles = "USER")
    void protectedPageAuthenticatedReturnsOk() throws Exception {
        mvc.perform(get("/secure/protected"))
                .andExpect(status().isOk());
    }

    // ── Security headers ────────────────────────────────────────────────

    @Test
    void homePageHasXFrameOptionsHeader() throws Exception {
        mvc.perform(get("/"))
                .andExpect(header().exists("X-Frame-Options"));
    }

    @Test
    void homePageHasXContentTypeOptionsHeader() throws Exception {
        mvc.perform(get("/"))
                .andExpect(header().exists("X-Content-Type-Options"));
    }

    @Test
    void homePageHasContentSecurityPolicyHeader() throws Exception {
        mvc.perform(get("/"))
                .andExpect(header().exists("Content-Security-Policy"));
    }

    // ── XSS демо: POST с csrf токен ─────────────────────────────────────

    @Test
    void xssDemoPostWithCsrfReturnsOk() throws Exception {
        mvc.perform(post("/xss-demo")
                        .with(csrf())
                        .param("content", "Тест коментар"))
                .andExpect(status().isOk());
    }

    @Test
    void xssDemoPostWithoutCsrfReturnsForbidden() throws Exception {
        mvc.perform(post("/xss-demo")
                        .param("content", "Тест коментар"))
                .andExpect(status().isForbidden());
    }

    // ── CSRF демо: POST с csrf токен ────────────────────────────────────

    @Test
    void csrfDemoPostWithCsrfReturnsOk() throws Exception {
        mvc.perform(post("/csrf-demo")
                        .with(csrf())
                        .param("data", "тестови данни"))
                .andExpect(status().isOk());
    }

    @Test
    void csrfDemoPostWithoutCsrfReturnsForbidden() throws Exception {
        mvc.perform(post("/csrf-demo")
                        .param("data", "тестови данни"))
                .andExpect(status().isForbidden());
    }
}
