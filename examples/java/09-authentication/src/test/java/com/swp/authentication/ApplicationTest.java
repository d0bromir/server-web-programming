package com.swp.authentication;

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
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.user;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 09 – Автентикация с Spring Security
 *
 * Стартиране:
 *   mvn test
 */
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

    @Test
    void loginPageIsPublic() throws Exception {
        mvc.perform(get("/login"))
           .andExpect(status().isOk());
    }

    @Test
    void homePageIsPublic() throws Exception {
        mvc.perform(get("/"))
           .andExpect(status().isOk());
    }

    @Test
    void dashboardRequiresAuthentication() throws Exception {
        mvc.perform(get("/dashboard"))
           .andExpect(status().is3xxRedirection());  // редирект към /login
    }

    @Test
    void adminPanelRequiresAuthentication() throws Exception {
        mvc.perform(get("/admin/panel"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    @WithMockUser(username = "user@demo.com", roles = "USER")
    void dashboardAccessibleByAuthenticatedUser() throws Exception {
        mvc.perform(get("/dashboard"))
           .andExpect(status().isOk());
    }

    @Test
    @WithMockUser(username = "admin@demo.com", roles = "ADMIN")
    void adminPanelAccessibleByAdmin() throws Exception {
        mvc.perform(get("/admin/panel"))
           .andExpect(status().isOk());
    }

    @Test
    @WithMockUser(username = "user@demo.com", roles = "USER")
    void adminPanelForbiddenForRegularUser() throws Exception {
        mvc.perform(get("/admin/panel"))
           .andExpect(status().isForbidden());
    }

    @Test
    void registerPageIsPublic() throws Exception {
        mvc.perform(get("/register"))
           .andExpect(status().isOk());
    }

    @Test
    void registerWithValidDataRedirects() throws Exception {
        mvc.perform(post("/register").with(csrf())
                .param("email", "newuser@test.com")
                .param("name", "Нов Потребител")
                .param("password", "Password1234"))
           .andExpect(status().is3xxRedirection());
    }
}
