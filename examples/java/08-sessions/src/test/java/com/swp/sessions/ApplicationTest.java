package com.swp.sessions;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 08 – Сесии и бисквитки
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
        mvc = MockMvcBuilders.webAppContextSetup(wac).build();
    }

    @Test
    void homePageReturns200() throws Exception {
        mvc.perform(get("/"))
           .andExpect(status().isOk())
           .andExpect(content().contentTypeCompatibleWith("text/html"));
    }

    @Test
    void setSessionAttributeRedirects() throws Exception {
        mvc.perform(post("/session/set")
                .param("key", "username")
                .param("value", "Иван"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void removeSessionAttributeRedirects() throws Exception {
        MockHttpSession session = new MockHttpSession();
        session.setAttribute("username", "Иван");

        mvc.perform(post("/session/remove").param("key", "username").session(session))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void invalidateSessionRedirects() throws Exception {
        mvc.perform(post("/session/invalidate"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void setCookieRedirects() throws Exception {
        mvc.perform(post("/cookie/set")
                .param("name", "theme")
                .param("value", "dark"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void deleteCookieRedirects() throws Exception {
        mvc.perform(post("/cookie/delete").param("name", "theme"))
           .andExpect(status().is3xxRedirection());
    }

    @Test
    void cookiesPageReturns200() throws Exception {
        mvc.perform(get("/cookies"))
           .andExpect(status().isOk());
    }

    @Test
    void sessionPersistsAcrossRequests() throws Exception {
        MockHttpSession session = new MockHttpSession();

        // Задаваме атрибут
        mvc.perform(post("/session/set")
                .param("key", "visitCount")
                .param("value", "1")
                .session(session));

        // Началната страница трябва да върне 200
        mvc.perform(get("/").session(session))
           .andExpect(status().isOk());
    }
}
