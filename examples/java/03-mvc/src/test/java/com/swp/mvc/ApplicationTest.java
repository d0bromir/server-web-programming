package com.swp.mvc;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.context.WebApplicationContext;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Автоматизирани тестове за Тема 03 – MVC архитектура
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
    void itemsListReturns200() throws Exception {
        mvc.perform(get("/items"))
           .andExpect(status().isOk());
    }

    @Test
    void itemDetailReturns200() throws Exception {
        mvc.perform(get("/items/1"))
           .andExpect(status().isOk());
    }

    @Test
    void itemNotFoundRedirects() throws Exception {
        mvc.perform(get("/items/9999"))
           .andExpect(status().is3xxRedirection());
    }
}
